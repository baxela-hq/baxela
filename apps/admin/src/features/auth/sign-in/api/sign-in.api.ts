import { postRequest } from '@/shared/lib/api-client'
import { getDeviceId } from '@/shared/lib/device-id'
import { type SignInRequest, type SignInResponse } from '../types/sign-in';
import { type SingleResponse } from '@/shared/types/common.types.ts'

const BASE_URL = "/auth/public/auth";


export async function signIn(request: SignInRequest): Promise<SignInResponse> {
  const { data } = await postRequest<SingleResponse<SignInResponse>, SignInRequest>(`${BASE_URL}/signin`, {
    ...request,
    device_name: getDeviceId(),
  });
  return data;
}