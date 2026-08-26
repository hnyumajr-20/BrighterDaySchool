import client from './client';

export const getStaffAttendance = (date) =>
  client.get('/attendance/staff', { params: date ? { date } : {} }).then((res) => res.data);
export const openCheckInWindow = () => client.post('/attendance/staff/window/open').then((res) => res.data);
export const markStaffAttendance = (staffId, status) =>
  client.post('/attendance/staff/mark', { staff_id: staffId, status }).then((res) => res.data);
export const getStaffAttendanceDailySummary = () =>
  client.get('/attendance/staff/daily-summary').then((res) => res.data);
