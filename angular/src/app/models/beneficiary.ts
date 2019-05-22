import {Address} from './address';

export interface Beneficiary {
  id: number;
  lastname: string;
  firstname: string;
  phone: string;
  address: Address;
}
