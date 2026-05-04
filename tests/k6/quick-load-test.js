import http from 'k6/http';
import { check } from 'k6';

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8001';
const BUFFER_ENDPOINT = __ENV.BUFFER_ENDPOINT || '/api/spool/buffer';

export const options = {
  duration: '10s',
  vus: 10,
};

export default function () {
  const res = http.get(`${BASE_URL}${BUFFER_ENDPOINT}`);

  check(res, {
    'status 200': (r) => r.status === 200,
  });
}
