<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { computed, onMounted } from 'vue';
import { Label } from '@/components/ui/label';
import { phoneNumberRegex } from '@/lib/utils';
import { usePhoneNumber } from '@/composables/usePhoneNumber';
import { CallState } from '@/types/call-log';

const props = defineProps<{
    destinationNumber: string; 
    callState: CallState;
    formState: {
        isProcessing: boolean;
        validationErrors: Record<string, string[]>;
        generalError: string | null;
        successMessage: string | null;
    };
    buttonAction: () => Promise<void> | void; 
}>()

const emit = defineEmits<{
    (event: 'open-modal', value: string): void;
    (event: 'update:destinationNumber', value: string): void;
}>();

const {
    phoneNumber,
    isVerified,
    error,
    isLoading,
    fetchPhoneNumber
} = usePhoneNumber();

onMounted(fetchPhoneNumber);

const isUserNumberVerified = computed(() => isVerified.value === true);

const localDestinationNumber = computed({
    get: () => props.destinationNumber,
    set: (value) => emit('update:destinationNumber', value)
});

const isDestinationNumberValid = computed(() => {
    return !localDestinationNumber.value || phoneNumberRegex.test(localDestinationNumber.value);
});

const displayDestinationNumberError = computed(() => {
    if (props.formState.validationErrors.destination_number) { 
        return props.formState.validationErrors.destination_number[0]; 
    }
    if (!isDestinationNumberValid.value && localDestinationNumber.value) { 
        return 'Please enter a valid Peruvian phone number (+51#########).';
    }
    return null; 
});

const isButtonDisabled = computed(() => {
    const baseDisabled =
    !isUserNumberVerified.value ||
    !localDestinationNumber.value ||
    !isDestinationNumberValid.value;
  const isParentProcessing = props.formState.isProcessing;
  switch (props.callState) {
    case 'idle':
    case 'ended':
    case 'failed':
      return baseDisabled || isParentProcessing;

    case 'connected':
    case 'ringing':
      return isParentProcessing;

    default:
      return true; // others states: confirming, initiating, etc.
  }
});

const handleButtonClick = () => { 
    if (!localDestinationNumber.value) {
        return;
    }
    if (!isDestinationNumberValid.value) {
        return;
    }
    if (!isUserNumberVerified.value) {
        console.warn('Attempted call initiation but user number is not verified.');
        return;
    }
    
    if (props.callState === 'idle' || props.callState === 'ended' || props.callState === 'failed') {
        emit('open-modal', localDestinationNumber.value); 
    } else if (props.callState === 'connected' || props.callState === 'ringing') {
        props.buttonAction();
    }
}


</script>

<template>
    <div class="flex flex-col gap-2 mb-4"> 
        <Label for="to">Number to call (+51#########)</Label>
        <Input
            id="to"
            v-model="localDestinationNumber"
            type="text"
            placeholder="e.g., +51987654321"
            class="input"
            :class="{ 
                'border-red-500': formState.validationErrors.destination_number || (localDestinationNumber && !isDestinationNumberValid) 
            }"
            :disabled="callState !== 'idle' && callState !== 'ended' && callState !== 'failed'" 
        />
        <div v-if="displayDestinationNumberError" class="text-red-500 text-sm mt-1">
            {{ displayDestinationNumberError }}
        </div>   
        <Button
            :disabled="isButtonDisabled"
            type="button" 
            class="btn"
            :class="{
                'bg-red-600 hover:bg-red-700': callState === 'connected' || callState === 'ringing'
            }"
            @click="handleButtonClick"
        >
            Llamar
        </Button>
    </div>
</template>