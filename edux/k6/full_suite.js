import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Trend, Rate } from 'k6/metrics';

export const errorRate = new Rate('system_errors');
export const sessionDuration = new Trend('user_session_duration');

export const options = {
  scenarios: {
    // Escenario 1: Tráfico continuo de navegación (15 VUs)
    browsing: {
      executor: 'constant-vus',
      vus: 15,
      duration: '30s',
      exec: 'browseScenario',
    },
    // Escenario 2: Ráfaga de autenticación (Spike)
    auth_spike: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '5s', target: 20 },
        { duration: '15s', target: 20 },
        { duration: '5s', target: 0 },
      ],
      exec: 'authScenario',
    },
  },
  thresholds: {
    'http_req_duration': ['p(95)<450', 'p(99)<900'],
    'http_req_failed': ['rate<0.01'],
    'system_errors': ['rate<0.01'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://nginx';

const ROLES = [
  { role: 'admin', email: 'admin@modux.demo', password: 'Admin2026!' },
  { role: 'coord', email: 'coord.enf@modux.demo', password: 'Coord2026!' },
  { role: 'docente', email: 'docente01@modux.demo', password: 'Docente2026!' },
  { role: 'estudiante', email: 'est001@modux.demo', password: 'Alumno2026!' },
];

export function browseScenario() {
  const start = Date.now();
  const user = ROLES[Math.floor(Math.random() * ROLES.length)];

  // Login
  const loginRes = http.post(
    `${BASE_URL}/api/v1/auth/login`,
    JSON.stringify({ email: user.email, password: user.password }),
    { headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' } }
  );

  const ok = check(loginRes, { 'login 200': (r) => r.status === 200 });
  errorRate.add(!ok);

  if (ok) {
    const token = JSON.parse(loginRes.body).data.token;
    const authHeaders = {
      headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' },
    };

    // Consultas autenticadas
    http.get(`${BASE_URL}/api/v1/auth/me`, authHeaders);
    http.get(`${BASE_URL}/api/v1/aula/notificaciones`, authHeaders);

    if (user.role === 'admin' || user.role === 'coord') {
      http.get(`${BASE_URL}/api/v1/dashboard/stats`, authHeaders);
      http.get(`${BASE_URL}/api/v1/admin/unidades-academicas`, authHeaders);
    }
  }

  sessionDuration.add(Date.now() - start);
  sleep(0.5);
}

export function authScenario() {
  const res = http.get(`${BASE_URL}/api/v1/health`);
  check(res, { 'health check 200': (r) => r.status === 200 });

  const loginRes = http.post(
    `${BASE_URL}/api/v1/auth/login`,
    JSON.stringify({ email: 'superadmin@modux.demo', password: 'SuperAdmin2026!' }),
    { headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' } }
  );
  check(loginRes, { 'superadmin login 200': (r) => r.status === 200 });

  sleep(0.3);
}
