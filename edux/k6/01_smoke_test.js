import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

export const errorRate = new Rate('errors');
export const healthDuration = new Trend('health_duration');
export const loginDuration = new Trend('login_duration');

export const options = {
  vus: 5,
  duration: '20s',
  thresholds: {
    'http_req_duration': ['p(95)<400', 'p(99)<800'],
    'http_req_failed': ['rate<0.01'],
    'errors': ['rate<0.01'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://nginx';

export default function () {
  // 1. Health check endpoint
  {
    const res = http.get(`${BASE_URL}/api/v1/health`);
    const success = check(res, {
      'health status is 200': (r) => r.status === 200,
      'health status is ok': (r) => {
        try {
          return JSON.parse(r.body).status === 'ok';
        } catch (_) {
          return false;
        }
      },
    });
    errorRate.add(!success);
    healthDuration.add(res.timings.duration);
  }

  sleep(0.5);

  // 2. Authentication flow (Admin login)
  {
    const payload = JSON.stringify({
      email: 'admin@modux.demo',
      password: 'Admin2026!',
    });
    const params = {
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    };

    const res = http.post(`${BASE_URL}/api/v1/auth/login`, payload, params);
    const success = check(res, {
      'login status is 200': (r) => r.status === 200,
      'has bearer token': (r) => {
        try {
          const json = JSON.parse(r.body);
          return json.data && typeof json.data.token === 'string';
        } catch (_) {
          return false;
        }
      },
    });
    errorRate.add(!success);
    loginDuration.add(res.timings.duration);
  }

  sleep(1);
}
