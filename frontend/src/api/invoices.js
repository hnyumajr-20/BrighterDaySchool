import client from './client';

export const listInvoices = (params = {}) => client.get('/invoices', { params }).then((res) => res.data);
export const getInvoice = (id) => client.get(`/invoices/${id}`).then((res) => res.data);
export const createInvoice = (data) => client.post('/invoices', data).then((res) => res.data);
export const payInvoice = (id, data) => client.post(`/invoices/${id}/pay`, data).then((res) => res.data);
