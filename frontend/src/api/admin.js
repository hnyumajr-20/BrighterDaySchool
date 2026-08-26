import client from './client';

// Staff
export const listStaff = () => client.get('/staff').then((res) => res.data);
export const createStaff = (formData) => client.post('/staff', formData).then((res) => res.data);
export const updateStaff = (id, data) => client.put(`/staff/${id}`, data).then((res) => res.data);
export const downloadStaffCv = (id) => client.get(`/staff/${id}/cv`, { responseType: 'blob' }).then((res) => res.data);
export const deleteStaff = (id) => client.delete(`/staff/${id}`);

// Classes
export const listClasses = () => client.get('/classes').then((res) => res.data);
export const createClass = (data) => client.post('/classes', data).then((res) => res.data);
export const updateClass = (id, data) => client.put(`/classes/${id}`, data).then((res) => res.data);
export const deleteClass = (id) => client.delete(`/classes/${id}`);

// Class subjects (subject + teacher assignments on a class)
export const listClassSubjects = (classId) => client.get(`/classes/${classId}/subjects`).then((res) => res.data);
export const createClassSubject = (classId, data) =>
  client.post(`/classes/${classId}/subjects`, data).then((res) => res.data);
export const updateClassSubject = (classId, classSubjectId, data) =>
  client.put(`/classes/${classId}/subjects/${classSubjectId}`, data).then((res) => res.data);
export const deleteClassSubject = (classId, classSubjectId) =>
  client.delete(`/classes/${classId}/subjects/${classSubjectId}`);

// Subjects
export const listSubjects = () => client.get('/subjects').then((res) => res.data);
export const createSubject = (data) => client.post('/subjects', data).then((res) => res.data);
export const updateSubject = (id, data) => client.put(`/subjects/${id}`, data).then((res) => res.data);
export const deleteSubject = (id) => client.delete(`/subjects/${id}`);

// Academic years
export const listAcademicYears = () => client.get('/academic-years').then((res) => res.data);
export const createAcademicYear = (data) => client.post('/academic-years', data).then((res) => res.data);
export const updateAcademicYear = (id, data) => client.put(`/academic-years/${id}`, data).then((res) => res.data);

// Semesters
export const listSemesters = (academicYearId) =>
  client.get('/semesters', { params: { academic_year_id: academicYearId } }).then((res) => res.data);
export const createSemester = (data) => client.post('/semesters', data).then((res) => res.data);
export const updateSemester = (id, data) => client.put(`/semesters/${id}`, data).then((res) => res.data);

// Periods
export const listPeriods = (semesterId) =>
  client.get('/periods', { params: { semester_id: semesterId } }).then((res) => res.data);
export const createPeriod = (data) => client.post('/periods', data).then((res) => res.data);
export const updatePeriod = (id, data) => client.put(`/periods/${id}`, data).then((res) => res.data);

// Finance
export const getFinanceOverview = () => client.get('/finance/overview').then((res) => res.data);
