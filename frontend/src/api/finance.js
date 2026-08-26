import client from './client';

export const listStudentBalances = () => client.get('/fee-transactions/students').then((res) => res.data);
export const listFeeTransactions = (studentId) =>
  client.get('/fee-transactions', { params: { student_id: studentId } }).then((res) => res.data);
export const recordFeeTransaction = (data) => client.post('/fee-transactions', data).then((res) => res.data);
export const getStudentBalance = (studentId) => client.get(`/students/${studentId}/balance`).then((res) => res.data);
export const getFeeDailyCollections = () => client.get('/fee-transactions/daily-collections').then((res) => res.data);

// Class fee installments
export const getClassInstallments = (classId) =>
  client.get(`/classes/${classId}/fee-installments`).then((res) => res.data);
export const saveClassInstallments = (classId, amounts, dueDates) =>
  client
    .post(`/classes/${classId}/fee-installments`, { ...(amounts ? { amounts } : {}), due_dates: dueDates })
    .then((res) => res.data);

// Staff salary payments
export const listSalaryPayments = (staffId) =>
  client.get('/salary-payments', { params: staffId ? { staff_id: staffId } : {} }).then((res) => res.data);
export const recordSalaryPayment = (data) => client.post('/salary-payments', data).then((res) => res.data);
export const getSalaryDailySummary = () => client.get('/salary-payments/daily-summary').then((res) => res.data);
export const getSalaryStaffOverview = () => client.get('/salary-payments/staff-overview').then((res) => res.data);

// Accountant dashboard summary
export const getAccountantSummary = () => client.get('/finance/accountant-summary').then((res) => res.data);
