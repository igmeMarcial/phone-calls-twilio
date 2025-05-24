
<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'; 
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import axios from 'axios'; 
import { isAxiosError } from 'axios'; 
import { usePhoneNumber } from '@/composables/usePhoneNumber';

const emit = defineEmits<{
    (event: 'numberVerified'): void; 
}>();

const code = ref(''); 

const formState = reactive({ 
    isProcessing: false, 
    validationErrors: {} as Record<string, string>, 
    generalError: null as string | null, 
    successMessage: null as string | null,
});

const {
  phoneNumber,
  isVerified,
  error,
  isLoading,
  fetchPhoneNumber
} = usePhoneNumber();

onMounted(fetchPhoneNumber);

const validateCode = (): boolean => {
    formState.validationErrors = {};
    formState.generalError = null; 
    if (!code.value) {
        formState.generalError = 'Verification code is required.'; 
        return false;
    }
    if (!/^[0-9]{6}$/.test(code.value)) {
        formState.generalError = 'The code must be exactly 6 digits.'; 
        return false;
    }
    formState.generalError = null;
    return true;
};


const submitForm = async () => {
    formState.validationErrors = {};
    formState.generalError = null;
    formState.successMessage = null;
    if (!validateCode()) {
        return; 
    }
    formState.isProcessing = true; 
    try {
        const response = await axios.post('/api/user/phone/verify', { code: code.value });
        console.log('Verification successful:', response.data);
        formState.successMessage = response.data.message || 'Phone number verified successfully.'; 
        emit('numberVerified');
    } catch (error) {
        console.error('Verification failed:', error);
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


const displayCodeError = computed(() => {
     if (formState.validationErrors.code) {
          return formState.validationErrors.code; 
     }
     return null; 
});


</script>

<template>
    <form @submit.prevent="submitForm"> 
        <div class="flex flex-col gap-2 mb-4">
            <Label for="code">Verification code</Label>
            <Input
                id="code"
                v-model="code"
                type="text"
                placeholder="e.g., 123456"
                class="input"
                 :class="{ 'border-red-500': displayCodeError }" 
            />
            <div v-if="displayCodeError" class="text-red-500 text-sm mt-1">
                {{ displayCodeError }}
            </div>
            <div v-else-if="formState.generalError" class="text-red-500 text-sm mt-1">
                 {{ formState.generalError }}
            </div>

            <div v-if="formState.successMessage" class="text-green-600 text-sm mt-1">
                {{ formState.successMessage }}
            </div>
        </div>

        <Button
            :disabled="formState.isProcessing || isVerified" 
            type="submit"
            class="btn cursor-pointer" 
        >
            {{ formState.isProcessing ? 'Verifying...' : 'Verify' }}
        </Button>
    </form>
</template>

