import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend } from 'k6/metrics';

export const throughput = new Trend('req_throughput');

export const options = {
  stages: [
    { duration: '5s', target: 10 },   // Calentamiento
    { duration: '10s', target: 50 },  // Pico súbito (Spike) a 50 VUs
    { duration: '15s', target: 50 },  // Mantener saturación
    { duration: '5s', target: 0 },    // Recuperación
  ],
  thresholds: {
    'http_req_duration': ['p(95)<800', 'p(99)<1500'],
    'http_req_failed': ['rate<0.05'], // Tolerancia < 5% en estrés severo
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://nginx';

export default function () {
  // 1. Health check rápido
  const resHealth = http.get(`${BASE_URL}/api/v1/health`);
  check(resHealth, {
    'health 200': (r) => r.status === 200,
  });

  // 2. Login ráfaga
  const payload = JSON.stringify({
    email: 'admin@modux.demo',
    password: 'Admin2026!',
  });
  const resLogin = http.post(`${BASE_URL}/api/v1/auth/login`, payload, {
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
  });

  check(resLogin, {
    'login 200': (r) => r.status === 200,
  });

  sleep(0.2);
}
