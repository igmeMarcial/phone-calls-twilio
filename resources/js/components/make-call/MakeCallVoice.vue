<script setup lang="ts">
import { ref, computed, reactive, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { isAxiosError } from 'axios';
import { Device } from '@twilio/voice-sdk';
import { markRaw } from 'vue'; 
import MakeCallVoiceModal from './MakeCallVoiceModal.vue';
import MakeCallVoiceForm from './MakeCallVoiceForm.vue';
import { CallState } from '@/types/call-log';


const destinationNumber = ref('');
const isModalOpen = ref(false);
const callState = ref<CallState>('idle');
const currentCall = ref<any>(null); 
const device = ref<Device | null>(null);
const voiceToken = ref<string | null>(null);

const formState = reactive({
    isProcessing: false,
    validationErrors: {} as Record<string, string[]>,
    generalError: null as string | null,
    successMessage: null as string | null,
});


const initializeTwilioDevice = async () => {
    formState.isProcessing = true;
    formState.generalError = null;
    formState.successMessage = 'Initializing voice service...';
    try {
        const response = await axios.get('/api/voice/token');
        voiceToken.value = response.data.token;
        const newDevice = new Device(voiceToken.value || '', {
            logLevel: 'SILENT',
            allowIncomingWhileBusy: false
        });
        device.value = markRaw(newDevice);
        device.value.on('ready', () => {
            console.log('Twilio Device is ready');
            formState.successMessage = 'Voice service ready';
            formState.isProcessing = false;
        });
        device.value.on('error', (error) => {
            console.error('Twilio Device error:', error);
            formState.generalError = 'Voice service error: ' + error.message;
            callState.value = 'failed';
        });
        device.value.on('incoming', (call) => {
            console.log('Incoming call from:', call.parameters.From);
            // Handle llamadas entrantes
        });
        device.value.on('tokenWillExpire', async () => {
            console.log('Token will expire, refreshing...');
            await refreshToken();
        });
        device.value.on('offline', () => {
            console.log('Twilio Device is offline');
            formState.generalError = 'Voice service is offline';
            callState.value = 'failed';
        });
        console.log('Twilio Device initialized successfully');
    } catch (error) {
        console.error('Failed to initialize Twilio Device:', error);
        if (isAxiosError(error) && error.response) {
            formState.generalError = error.response.data.message || 'Failed to initialize voice service';
        } else {
            formState.generalError = 'Failed to initialize voice service';
        }
        formState.successMessage = null;
        callState.value = 'failed';
        formState.isProcessing = false;
    }finally {
        if (callState.value === 'idle') {
            formState.isProcessing = false;
        }
    }

};


const refreshToken = async () => {
    if (!device.value) {
        console.error('Twilio Device not initialized');
        return;
    }
    try {
        const response = await axios.get('/api/voice/token');
        voiceToken.value = response.data.token;
        if (device.value) {
            device.value.updateToken(voiceToken.value || '');
        }
    } catch (error) {
        console.error('Failed to refresh token:', error);
    }
};


const setupCallEventListeners = (call: any) => {
    if (!call) {
        console.error('No call to set up event listeners');
        return;
    }


    call.on('accept', () => {
        console.log('Call accepted');
        callState.value = 'connected';
        formState.successMessage = 'Call connected';
    });

    call.on('disconnect', () => {
        console.log('Call disconnected');
        callState.value = 'ended';
        formState.successMessage = 'Call ended';
        currentCall.value = null;
    });

    call.on('cancel', () => {
        console.log('Call cancelled');
        callState.value = 'ended';
        formState.successMessage = 'Call cancelled';
        currentCall.value = null;
    });

    call.on('reject', () => {
        console.log('Call rejected');
        callState.value = 'failed';
        formState.generalError = 'Call was rejected';
        currentCall.value = null;
    });

    call.on('error', (error: any) => {
        console.error('Call error:', error);
        callState.value = 'failed';
        formState.generalError = 'Call error: ' + error.message;
        currentCall.value = null;
    });

    call.on('ringing', () => {
        console.log('Call is ringing');
        callState.value = 'ringing';
        formState.successMessage = 'Ringing...';
    });
};

const handleOpenModal = (number: string) => {
    destinationNumber.value = number;
    resetFormState();
    callState.value = 'confirming';
    isModalOpen.value = true;
};

const handleCancelCall = () => {
    if (callState.value === 'connected' || callState.value === 'ringing') {
        endCall();
    }
    callState.value = 'idle';
    console.log('Call cancelled by user.');
    resetFormState();
    isModalOpen.value = false;
};

const handleConfirmCall = async () => {
    callState.value = 'requesting_permission';
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        stream.getTracks().forEach(track => track.stop());
        await makeCall();
    } catch (err) {
        callState.value = 'failed';
        if (err instanceof Error) {
            formState.generalError = 'Microphone access denied: ' + err.message;
        } else {
            formState.generalError = 'Microphone access failed.';
        }
        formState.isProcessing = false;
    }
};

const makeCall = async () => {
    if (!device.value) {
        formState.generalError = 'Voice service not ready';
        callState.value = 'failed';
        return;
    }
    resetFormState();
    callState.value = 'initiating';
    formState.isProcessing = true
    try {
        console.log('Making call to:', destinationNumber.value);
        const call = await device.value.connect({
            params: {
                To: destinationNumber.value
            }
        });
        currentCall.value = markRaw(call);
        setupCallEventListeners(call);
        console.log('Call initiated successfully');
        formState.successMessage = 'Call initiated';
        
    } catch (error) {
        callState.value = 'failed';
        currentCall.value = null;
        if (error instanceof Error) {
            formState.generalError = 'Call failed: ' + error.message;
        } else {
            formState.generalError = 'Call failed due to unknown error';
        }
        
        formState.successMessage = null;
    } finally {
        formState.isProcessing = false;
    }
};

const endCall = async () => {
    if (!currentCall.value) {
        console.warn('No active call to end.');
        return;
    }
    formState.isProcessing = true;
    callState.value = 'ending';
    try {
        console.log('Ending call...');
        await currentCall.value.disconnect();
        formState.successMessage = 'Call ended';
        callState.value = 'ended';
        currentCall.value = null;
        
    } catch (error) {
        callState.value = 'connected';
        
        if (error instanceof Error) {
            formState.generalError = 'Failed to end call: ' + error.message;
        } else {
            formState.generalError = 'Failed to end call';
        }
        formState.successMessage = null;
        
    } finally {
        formState.isProcessing = false;
    }
};

const resetFormState = () => {
    formState.validationErrors = {};
    formState.generalError = null;
    formState.successMessage = null;
};

const buttonAction = computed(() => {
    if (callState.value === 'connected' || callState.value === 'ringing') {
        return endCall;
    } else {
        return () => {};
    }
});

// Lifecycle hooks
onMounted(async () => {
    await initializeTwilioDevice();
});

onUnmounted(() => {
    if (currentCall.value) {
        currentCall.value.disconnect();
    }
    if (device.value) {
        device.value.destroy();
    }
});
</script>

<template>
    <MakeCallVoiceForm
        v-model:destination-number="destinationNumber"
        :call-state="callState"
        :form-state="formState"
        :button-action="buttonAction"
        @open-modal="handleOpenModal(destinationNumber)"
    />
    <MakeCallVoiceModal
        :open="isModalOpen"
        :destination-number="destinationNumber"
        :call-state="callState"
        :form-state="formState"
        @confirm-call="handleConfirmCall"
        @cancel-call="handleCancelCall"
    />
</template>