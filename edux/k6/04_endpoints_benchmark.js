import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend } from 'k6/metrics';

export const latencyHealth = new Trend('t_health');
export const latencyLogin = new Trend('t_login');
export const latencyMe = new Trend('t_me');
export const latencyProgramas = new Trend('t_programas');
export const latencyPeriodos = new Trend('t_periodos');

export const options = {
  vus: 10,
  duration: '25s',
  thresholds: {
    't_health': ['p(95)<100'],
    't_login': ['p(95)<400'],
    't_me': ['p(95)<250'],
    't_programas': ['p(95)<250'],
    't_periodos': ['p(95)<250'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://nginx';

export function setup() {
  // Obtener token inicial de administración
  const res = http.post(
    `${BASE_URL}/api/v1/auth/login`,
    JSON.stringify({ email: 'admin@modux.demo', password: 'Admin2026!' }),
    { headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' } }
  );
  if (res.status === 200) {
    return { token: JSON.parse(res.body).data.token };
  }
  return { token: null };
}

export default function (data) {
  const headers = {
    headers: {
      'Authorization': `Bearer ${data.token}`,
      'Accept': 'application/json',
    },
  };

  // Endpoint 1: Health
  const r1 = http.get(`${BASE_URL}/api/v1/health`);
  check(r1, { 'health ok': (r) => r.status === 200 });
  latencyHealth.add(r1.timings.duration);

  // Endpoint 2: Me
  const r2 = http.get(`${BASE_URL}/api/v1/auth/me`, headers);
  check(r2, { 'me ok': (r) => r.status === 200 });
  latencyMe.add(r2.timings.duration);

  // Endpoint 3: Programas (Unidades Académicas)
  const r3 = http.get(`${BASE_URL}/api/v1/admin/unidades-academicas`, headers);
  check(r3, { 'programas ok': (r) => r.status === 200 });
  latencyProgramas.add(r3.timings.duration);

  // Endpoint 4: Periodos
  const r4 = http.get(`${BASE_URL}/api/v1/academico/periodos`, headers);
  check(r4, { 'periodos ok': (r) => r.status === 200 });
  latencyPeriodos.add(r4.timings.duration);

  sleep(0.3);
}
