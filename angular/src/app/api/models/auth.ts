import {User} from './user';

export interface Auth {
  user?: User;
  trusted_ip: boolean;
}
