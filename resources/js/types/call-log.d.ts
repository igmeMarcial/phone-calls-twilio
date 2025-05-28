

export interface CallLog {
    id: number; 
    user_id: number;
    phone_number_id: number | null; 
    destination_number: string;
    twilio_call_sid: string | null; 
    status: string;
    start_time: string | null; 
    end_time: string | null;
    duration: number | null; 
    price: string | null; 
    error_message: string | null;
    created_at: string;
    updated_at: string;
    // user?: User; 
    // phoneNumber?: PhoneNumber;
}


export type CallState = 
  | 'idle' 
  | 'confirming' 
  | 'requesting_permission' 
  | 'initiating' 
  | 'ringing' 
  | 'connected' 
  | 'ended' 
  | 'ending' 
  | 'failed';