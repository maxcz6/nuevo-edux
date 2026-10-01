import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

export const errorRate = new Rate('custom_errors');
export const apiLatency = new Trend('api_latency');

export const options = {
  stages: [
    { duration: '10s', target: 10 }, // Ramp-up a 10 VUs
    { duration: '20s', target: 25 }, // Carga sostenida a 25 VUs
    { duration: '10s', target: 0 },  // Ramp-down a 0
  ],
  thresholds: {
    'http_req_duration': ['p(90)<350', 'p(95)<600', 'p(99)<1200'],
    'http_req_failed': ['rate<0.02'], // Error rate menor al 2%
    'custom_errors': ['rate<0.02'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://nginx';

const ACCOUNTS = [
  { role: 'admin', email: 'admin@modux.demo', password: 'Admin2026!' },
  { role: 'coord', email: 'coord.enf@modux.demo', password: 'Coord2026!' },
  { role: 'docente', email: 'docente01@modux.demo', password: 'Docente2026!' },
  { role: 'estudiante', email: 'est001@modux.demo', password: 'Alumno2026!' },
];

export default function () {
  const user = ACCOUNTS[Math.floor(Math.random() * ACCOUNTS.length)];

  group(`Sesión de ${user.role}`, function () {
    // 1. Iniciar sesión
    const loginPayload = JSON.stringify({
      email: user.email,
      password: user.password,
    });
    const loginRes = http.post(`${BASE_URL}/api/v1/auth/login`, loginPayload, {
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    });

    const loginOk = check(loginRes, {
      'login 200': (r) => r.status === 200,
    });
    errorRate.add(!loginOk);
    apiLatency.add(loginRes.timings.duration);

    if (!loginOk) return;

    let token = '';
    try {
      token = JSON.parse(loginRes.body).data.token;
    } catch (_) {
      return;
    }

    const authHeaders = {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
      },
    };

    sleep(0.5);

    // 2. Consultar perfil propio (todos los roles)
    const meRes = http.get(`${BASE_URL}/api/v1/auth/me`, authHeaders);
    const meOk = check(meRes, {
      'me status 200': (r) => r.status === 200,
    });
    errorRate.add(!meOk);
    apiLatency.add(meRes.timings.duration);

    sleep(0.5);

    // 3. Consultar notificaciones (todos los roles)
    const notifRes = http.get(`${BASE_URL}/api/v1/aula/notificaciones`, authHeaders);
    const notifOk = check(notifRes, {
      'notificaciones status 200': (r) => r.status === 200,
    });
    errorRate.add(!notifOk);
    apiLatency.add(notifRes.timings.duration);

    sleep(0.5);

    // 4. Endpoints según permisos de rol
    if (user.role === 'admin' || user.role === 'coord' || user.role === 'docente') {
      const statsRes = http.get(`${BASE_URL}/api/v1/dashboard/stats`, authHeaders);
      const statsOk = check(statsRes, {
        'dashboard stats 200': (r) => r.status === 200,
      });
      errorRate.add(!statsOk);
      apiLatency.add(statsRes.timings.duration);
    }

    sleep(1);
  });
}
