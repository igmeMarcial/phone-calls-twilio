 <script setup lang="ts">

import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import axios from 'axios';
import { computed, onMounted, reactive, ref } from 'vue';
import { UserPhoneNumber } from '@/types';
import { usePhoneNumber } from '@/composables/usePhoneNumber';
import { phoneNumberRegex } from '@/lib/utils';


const emit = defineEmits<{
    (event: 'numberRegistered', data: UserPhoneNumber): void; 
    (event: 'numberDeleted'): void; 
    (event: 'feedback', data: { type: 'success' | 'error'; message: string }): void; 
}>();


const formState = reactive({ 
    isLoading: true, 
    isProcessing: false, 
    error: null as string | null,
    validationErrors: {} as Record<string, string>, 
    success: null as string | null, 
});


const {
  phoneNumber,
  originalPhoneNumber,
  isVerified,
  error,
  isLoading,
  fetchPhoneNumber
} = usePhoneNumber();

onMounted(fetchPhoneNumber);

const isValidFormat = computed(() => {
    return !phoneNumber.value || phoneNumberRegex.test(phoneNumber.value);
});

const isRequired = computed(() => !phoneNumber.value);


const displayPhoneNumberError = computed(() => {
    if (formState.validationErrors.phone_number) {
        return formState.validationErrors.phone_number; 
    }
    if (!isValidFormat.value) {
        return 'Please enter a valid Peruvian phone number (+51#########).'; 
    }
    return null;
});

const hasChangedPhoneNumber = computed(() => phoneNumber.value !== originalPhoneNumber.value);

const submitForm = async () => {
    formState.error = null;
    formState.validationErrors = {};
    formState.success = null;
    if (isRequired.value) {
        formState.error = 'The phone number field is required.';
        return; 
    }
    if (!isValidFormat.value) {
        formState.error = 'Please enter a valid Peruvian phone number (+51#########).';
        return; 
    }
     if (!hasChangedPhoneNumber.value && originalPhoneNumber.value !== '') {
         console.log('Number has not changed, no submission needed.');
         return;
     }
    formState.isProcessing = true; 

    try {
        const response = await axios.post('/api/user/phone/register-request', { phone_number: phoneNumber.value });
        const updatedPhoneData = response.data.phone_number_data as UserPhoneNumber | null; 
        if (updatedPhoneData) {
             originalPhoneNumber.value = updatedPhoneData.number;
             isVerified.value = updatedPhoneData.is_verified; 
        } else {
             originalPhoneNumber.value = phoneNumber.value;
             isVerified.value = false;
        }
        emit('feedback', { type: 'success', message:'Verification code sent.' });
        formState.success = response.data.message || 'Verification code sent.'; 
        emit('numberRegistered', response.data); 
    } catch (error) {
        console.error('Registration request failed:', error);
        if (axios.isAxiosError(error) && error.response) {
            if (error.response.status === 422) {
                formState.validationErrors = error.response.data.errors || {};
                formState.error = error.response.data.message || 'Validation failed.';
            } else {
                formState.error = error.response.data.message || `Error: ${error.response.status} ${error.response.statusText}`;
            }
        } else {
            formState.error = 'Network error or unexpected issue.';
        }
         formState.success = null; 

    } finally {
        formState.isProcessing = false; 
};

}


const deletePhoneNumber = async () => {
     if (!originalPhoneNumber.value) {
         console.log('No number to delete.');
         return;
     }
    formState.isProcessing = true; 
    formState.error = null;
    formState.validationErrors = {};
    formState.success = null;
    try {
        const response = await axios.post('/api/user/phone/delete'); 
        console.log('Phone number deleted:', response.data);

        formState.success = response.data.message || 'Phone number deleted.'; 
        originalPhoneNumber.value = '';
        phoneNumber.value = ''; 
        isVerified.value = false;
        emit('numberDeleted');
    } catch (error) {
         console.error('Failed to delete phone number:', error);
         if (axios.isAxiosError(error) && error.response) {
              formState.error = error.response.data.message || `Error: ${error.response.status} ${error.response.statusText}`;
         } else {
             formState.error = 'Network error or unexpected issue.';
         }
         formState.success = null; 

    } finally {
        formState.isProcessing = false; 
    }
  };


const isRegisterButtonDisabled = computed(() => {
    return formState.isProcessing || isRequired.value || !isValidFormat.value || (hasChangedPhoneNumber.value === false && originalPhoneNumber.value !== '');
});

const isDeleteButtonDisabled = computed(() => {
    return formState.isProcessing || !originalPhoneNumber.value;
});

</script>

<template>
    <form @submit.prevent="submitForm">
        <div class="flex flex-col gap-2">
            <Label for="phone_number">Phone number: (+51#########)</Label>
            <Input
                id="phone_number"
                v-model="phoneNumber"
                type="text"
                placeholder="Your phone number"
                class="input"
                 :class="{ 'border-red-500': formState.validationErrors.phone_number || (!isValidFormat && phoneNumber) }" 
            />
             <div v-if="isLoading">Loading status...</div>
             <div v-else-if="isVerified" class="text-green-600 text-sm">
                 Your number ({{ originalPhoneNumber }}) is verified.
             </div>
             <div v-else-if="originalPhoneNumber" class="text-yellow-600 text-sm">
                  Number ({{ originalPhoneNumber }}) is not verified.
             </div>
             <div v-else class="text-gray-500 text-sm">
                 No phone number registered yet.
             </div>
            <div v-if="displayPhoneNumberError" class="text-red-500 text-sm mt-1">
                {{ displayPhoneNumberError }}
            </div>
             <div v-if="formState.error && !displayPhoneNumberError" class="text-red-500 text-sm mt-1">
                 {{ formState.error }}
             </div>
             <div v-if="formState.success" class="text-green-600 text-sm mt-1">
               {{ formState.success }}
             </div>

        </div>

        <div class="flex gap-2 mt-4">
            <Button
                :disabled="isRegisterButtonDisabled"
                type="submit"
                class="btn cursor-pointer"
            >
                {{ formState.isProcessing && !isDeleteButtonDisabled ? 'Sending...' : (originalPhoneNumber && hasChangedPhoneNumber ? 'Update Number' : 'Register Number') }}
            </Button>
            <Button
                v-if="originalPhoneNumber"
                type="button"
                @click="deletePhoneNumber"
                variant="destructive"
                :disabled="isDeleteButtonDisabled"
                class="btn cursor-pointer"
            >
                 {{ formState.isProcessing && isDeleteButtonDisabled ? 'Deleting...' : 'Delete Number' }}
            </Button>
        </div>

    </form>
</template>
 
  
 


