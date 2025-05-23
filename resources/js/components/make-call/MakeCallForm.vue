<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { ref, computed, reactive, onMounted } from 'vue';
import { Label } from '@/components/ui/label';
import axios from 'axios';
import { isAxiosError } from 'axios';
import { phoneNumberRegex } from '@/lib/utils';
import { usePhoneNumber } from '@/composables/usePhoneNumber';


const emit = defineEmits<{
    (event: 'callInitiated', data: { call_sid: string; log_id: number }): void; 
}>();


const {
  phoneNumber,
  isVerified,
  error,
  isLoading,
  fetchPhoneNumber
} = usePhoneNumber();

onMounted(fetchPhoneNumber);


const isUserNumberVerified = computed(() =>  isVerified.value === true);
const destinationNumber = ref('');

const formState = reactive({
    isProcessing: false, 
    validationErrors: {} as Record<string, string>, 
    generalError: null as string | null, 
    successMessage: null as string | null, 
});



const isDestinationNumberValid = computed(() => {
   return !destinationNumber.value || phoneNumberRegex.test(destinationNumber.value);
});

const displayDestinationNumberError = computed(() => {
    if (formState.validationErrors.destination_number) { 
        return formState.validationErrors.destination_number[0]; 
    }
    if (!isDestinationNumberValid.value && destinationNumber.value) { 
        return 'Please enter a valid Peruvian phone number (+51#########).';
    }
    return null; 
});


const submitForm = async () => {
    formState.validationErrors = {};
    formState.generalError = null;
    formState.successMessage = null;
    if (!destinationNumber.value) {
        formState.generalError = 'Number to call is required.';
        return; 
    }
    if (!isDestinationNumberValid.value) {
        formState.generalError = 'Please enter a valid Peruvian phone number (+51#########).'; 
        return; 
    }
     if (!isUserNumberVerified.value) {
         formState.generalError = 'Your phone number must be registered and verified before making calls.';
         console.warn('Attempted call initiation but user number is not verified.');
         return;
     }
    formState.isProcessing = true; 

    try {
        const response = await axios.post('/api/call', { destination_number: destinationNumber.value });
        console.log('Call initiated successfully:', response.data);
        formState.successMessage = response.data.message || 'Call initiated.'; 
        emit('callInitiated', response.data); 
    } catch (error) {
        console.error('Call initiation failed:', error);
        if (isAxiosError(error) && error.response) {
            if (error.response.status === 422) {
                formState.validationErrors = error.response.data.errors || {};
                formState.generalError = error.response.data.message || 'Validation failed on server.';
            } else {
                formState.generalError = error.response.data.message || `Error: ${error.response.status} ${error.response.statusText}`;
            }
        } else {
            formState.generalError = 'Network error or unexpected issue.';
        }
        formState.successMessage = null;

    } finally {
        formState.isProcessing = false; 
    }
};

const isButtonDisabled = computed(() => {
    return !isUserNumberVerified.value || formState.isProcessing || !destinationNumber.value || !isDestinationNumberValid.value;
});


</script>

<template>
   <form @submit.prevent="submitForm" > 
        <div class="flex flex-col gap-2 mb-4"> 
            <Label for="to">Number to call (+51#########)</Label>
            <Input
                id="to"
                v-model="destinationNumber"
                type="text"
                placeholder="e.g., +51987654321"
                class="input"
                 :class="{ 'border-red-500': formState.validationErrors.destination_number || (!isDestinationNumberValid && destinationNumber) }" 
            />
            <div v-if="displayDestinationNumberError" class="text-red-500 text-sm mt-1">
                {{ displayDestinationNumberError }}
            </div>
            <div v-else-if="isUserNumberVerified" class="text-green-600 text-sm">
                 You can to do a test call.
             </div>
            <div v-else-if="formState.generalError" class="text-red-500 text-sm mt-1">
                 {{ formState.generalError }}
            </div>
            <div v-if="formState.successMessage" class="text-green-600 text-sm mt-1">
                {{ formState.successMessage }}
            </div>
        </div>
         <div v-if="!isUserNumberVerified" class="text-orange-600 text-sm mb-4">
             Your phone number must be registered and verified to make calls.
         </div>
        <Button
            :disabled="isButtonDisabled"
            type="submit"
            class="btn" 
        >
            {{ formState.isProcessing ? 'Calling...' : 'Call' }}
        </Button>
    </form>
  </template>
  
