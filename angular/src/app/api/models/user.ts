import {Beneficiary} from './beneficiary';

export interface User {
  id: number;
  username: string;
  email: string;
  enabled: boolean;
  last_login: string;
  roles: string[];
  beneficiary?: Beneficiary;
}
