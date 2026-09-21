import axios from './axios';

export const verificationService = {
  submitDocuments: (formData: FormData) => 
    axios.post('/verification/submit', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    }).then(res => res.data),
  
  getVerificationStatus: () => 
    axios.get('/verification/status').then(res => res.data),

  checkEligibility: () => 
    axios.get('/verification/eligibility').then(res => res.data),
};
