import client from './client';

// The academic year/semester/period currently open — available to every
// authenticated role, not just admin.
export const getCurrentAcademicContext = () => client.get('/academic-years/current').then((res) => res.data);
