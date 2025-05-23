<script setup lang="ts">
import { ref, onMounted } from 'vue';
import type { CallLog } from '@/types/call-log';
import axios from 'axios';

const callLogs = ref<CallLog[]>([]);
const loadingLogs = ref(true);
const logsError = ref<string | null>(null);

const fetchCallLogs = async () => {
    loadingLogs.value = true;
    logsError.value = null;
    try {
    const response = await axios.get('/api/user/call-logs', {
      headers: {
        Accept: 'application/json',
      },
    });
    callLogs.value = response.data;
  } catch (error) {
    logsError.value = 'Failed to load call history.';
    console.error(error);
  } finally {
    loadingLogs.value = false;
  }
};

onMounted(() => {
    fetchCallLogs();
});
</script>

<template>
    <section class="mt-6 ">
        <h2 class="text-xl font-semibold mb-4">Call History</h2>

        <div v-if="loadingLogs">Loading call history...</div>
        <div v-else-if="logsError" class="text-red-500">{{ logsError }}</div>
        <div v-else-if="callLogs.length === 0">No calls yet.</div>
        <div v-else>
            <ul class="space-y-4">
                <li
                    v-for="log in callLogs"
                    :key="log.id"
                    class="border-b pb-4 last:border-b-0 last:pb-0"
                >
                    <p><strong>To:</strong> {{ log.destination_number }}</p>
                    <p><strong>Status:</strong> {{ log.status }}</p>
                    <p v-if="log.duration !== null"><strong>Duration:</strong> {{ log.duration }} seconds</p>
                    <p v-if="log.price !== null"><strong>Price:</strong> {{ log.price }}</p>
                    <p v-if="log.error_message"><strong>Error:</strong> {{ log.error_message }}</p>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ new Date(log.created_at).toLocaleString() }}
                    </p>
                </li>
            </ul>
        </div>
    </section>
</template>