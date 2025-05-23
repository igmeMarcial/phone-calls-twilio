import { ref } from 'vue';
import axios from 'axios';

export function usePhoneNumber() {
    const phoneNumber = ref('');
    const originalPhoneNumber = ref('');
    const isVerified = ref(false);
    const error = ref<string | null>(null);
    const isLoading = ref(false);

  
    const fetchPhoneNumber = async () => {
      isLoading.value = true;
      error.value = null;
  
      try {
        const response = await axios.get('/api/user/phone');
        const data = response.data || null;
  
        if (data?.phone_number) {
          phoneNumber.value = data.phone_number;
          originalPhoneNumber.value = data.phone_number;
          isVerified.value = data.is_verified;
        } else {
          phoneNumber.value = '';
          originalPhoneNumber.value = '';
          isVerified.value = false;
        }
      } catch (err) {
        console.error('Error fetching phone number:', err);
        error.value = 'Failed to load phone number information.';
      } finally {
        isLoading.value = false;
      }
    };
  
    return {
      phoneNumber,
      originalPhoneNumber,
      isVerified,
      error,
      isLoading,
      fetchPhoneNumber
    };
  }