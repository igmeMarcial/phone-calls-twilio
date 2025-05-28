<script lang="ts" setup>
import { computed } from 'vue';
import { Dialog, DialogContent, DialogTitle, DialogDescription} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { CallState } from '@/types/call-log';

const props = defineProps<{
    open: boolean; 
    destinationNumber: string; 
    callState: CallState; 
    formState: { 
        isProcessing: boolean;
        validationErrors: Record<string, string[]>;
        generalError: string | null;
        successMessage: string | null;
    };
}>();

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
    (event: 'confirm-call'): void;
    (event: 'cancel-call'): void; 
}>();

const handleConfirm = () => {
    emit('confirm-call');
};

const handleCancel = () => {
    emit('cancel-call');
    emit('update:open', false);
};

const modalContentState = computed(() => {
    switch (props.callState) {
        case 'confirming':
            return {
                title: 'Confirm Call',
                description: `Are you sure you want to call ${props.destinationNumber}?`,
                showPermissionNotice: true,
                showActions: true, 
                showProcessing: false,
                isClosable: !props.formState.isProcessing,
                showSpinner: false
            };
        case 'requesting_permission':
            return {
                title: 'Requesting Microphone Permission',
                description: 'Please grant microphone permission in your browser to make the call.',
                showPermissionNotice: false,
                showActions: false, 
                showProcessing: true, 
                isClosable: false,
                showSpinner: true
            };
        case 'initiating':
            return {
                title: 'Initiating Call',
                description: `Connecting to ${props.destinationNumber}...`,
                showPermissionNotice: false,
                showActions: false,
                showProcessing: true,
                isClosable: false,
                showSpinner: true
            };
        case 'ringing':
            return {
                title: 'Call Ringing',
                description: `Calling ${props.destinationNumber}...`,
                showPermissionNotice: false,
                showActions: true,
                showProcessing: true,
                isClosable: false,
                showSpinner: false
            };
        case 'connected':
            return {
                title: 'Call Connected',
                description: `Connected to ${props.destinationNumber}`,
                showPermissionNotice: false,
                showActions: true,
                showProcessing: false,
                isClosable: false,
                showSpinner: false
            };
        case 'ending':
            return {
                title: 'Ending Call',
                description: 'Disconnecting...',
                showPermissionNotice: false,
                showActions: false,
                showProcessing: true,
                isClosable: false,
                showSpinner: true
            };
        case 'failed':
            return {
                title: 'Call Failed',
                description: props.formState.generalError || 'An error occurred during the call.',
                showPermissionNotice: false,
                showActions: true, 
                showProcessing: false,
                isClosable: true,
                showSpinner: false
            };
        case 'ended':
            return {
                title: 'Call Ended',
                description: props.formState.successMessage || 'The call has ended.',
                showPermissionNotice: false,
                showActions: true,
                showProcessing: false,
                isClosable: true,
                showSpinner: false
            };
        default:
            return {
                title: 'Call',
                description: '...',
                showPermissionNotice: false,
                showActions: false,
                showProcessing: false,
                isClosable: true,
                showSpinner: false
            };
    }
});


</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent :is-closable="modalContentState.isClosable">
            <DialogTitle>{{ modalContentState.title }}</DialogTitle>
            <DialogDescription>{{ modalContentState.description }}</DialogDescription>
            <div v-if="modalContentState.showPermissionNotice" class="text-sm text-gray-600 mb-4">
                This will require microphone permission from your browser.
            </div>
            <div v-if="modalContentState.showSpinner" class="flex justify-center items-center py-4">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            </div>
            <div v-if="callState === 'ringing'" class="flex justify-center items-center py-4">
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-blue-600 rounded-full animate-pulse"></div>
                    <div class="w-3 h-3 bg-blue-600 rounded-full animate-pulse" style="animation-delay: 0.2s"></div>
                    <div class="w-3 h-3 bg-blue-600 rounded-full animate-pulse" style="animation-delay: 0.4s"></div>
                </div>
            </div>
            <div v-if="callState === 'connected'" class="flex justify-center items-center py-4">
                <div class="flex items-center space-x-2 text-green-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <span class="font-medium">Call Active</span>
                </div>
            </div>
            <div v-if="modalContentState.showActions" class="flex justify-end gap-4 mt-4">
                <Button 
                    v-if=" callState === 'connected' || callState === 'ringing' "
                    variant="destructive" 
                    @click="handleCancel"
                >
                    {{ callState === 'connected' ? 'Hang Up' : 'Cancel Call' }}
                </Button>
                <Button v-if="callState === 'confirming'" variant="outline" @click="handleCancel">
                    Cancel
                </Button>
                <Button v-if="callState === 'confirming'" @click="handleConfirm">
                    Confirm Call
                </Button>
                <Button v-if="callState === 'failed' || callState === 'ended'" @click="handleCancel">
                    Close
                </Button>
            </div>
            <div v-if="modalContentState.showProcessing && !modalContentState.showSpinner" 
                 class="text-center text-sm text-gray-500 mt-4">
                {{ props.formState.isProcessing ? 'Processing...' : 'Please wait...' }}
            </div>
            <div v-if="callState !== 'confirming' && (props.formState.generalError || props.formState.successMessage)" 
                 class="mt-4">
                <div v-if="props.formState.generalError" class="text-red-500 text-sm">
                    {{ props.formState.generalError }}
                </div>
                <div v-if="props.formState.successMessage" class="text-green-600 text-sm">
                    {{ props.formState.successMessage }}
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>