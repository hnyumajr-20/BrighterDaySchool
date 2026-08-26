import client from './client';

// Parents / guardians
export const lookupParentByPhone = (phone) => client.get('/parents', { params: { phone } }).then((res) => res.data);
export const createParent = (data) => client.post('/parents', data).then((res) => res.data);

// Admissions / students
export const listAdmissions = (status) =>
  client.get('/admissions', { params: status ? { status } : {} }).then((res) => res.data);
export const getAdmissionsDailySummary = () => client.get('/admissions/daily-summary').then((res) => res.data);
export const listStudents = (status) =>
  client.get('/students', { params: status ? { status } : {} }).then((res) => res.data);
export const getStudent = (id) => client.get(`/students/${id}`).then((res) => res.data);
export const createStudent = (formData) =>
  client.post('/students', formData, { headers: { 'Content-Type': 'multipart/form-data' } }).then((res) => res.data);
export const approveStudent = (id) => client.post(`/students/${id}/approve`).then((res) => res.data);
export const rejectStudent = (id) => client.post(`/students/${id}/reject`);
export const updateStudent = (id, data) => client.put(`/students/${id}`, data).then((res) => res.data);
export const deleteStudent = (id) => client.delete(`/students/${id}`);
export const updateStudentClass = (id, classId) =>
  client.put(`/students/${id}/class`, { class_id: classId }).then((res) => res.data);
export const downloadTranscript = (id) =>
  client.get(`/students/${id}/transcript`, { responseType: 'blob' }).then((res) => res.data);
export const downloadAdmissionLetter = (id) =>
  client.get(`/students/${id}/admission-letter`, { responseType: 'blob' }).then((res) => res.data);
