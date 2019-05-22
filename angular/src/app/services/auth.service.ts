import {Injectable} from '@angular/core';
import {HttpClient} from '@angular/common/http';
import {Observable} from 'rxjs';
import {Auth} from '../models/auth';
import {User} from '../models/user';
import {map} from 'rxjs/operators';

@Injectable({
  providedIn: 'root'
})
export class AuthService {

  constructor(private http: HttpClient) {
  }

  get currentUserValue(): Observable<Auth> {
    return this.http.get<User>('/api/user').pipe(map(u => {
      return {user: u} as Auth
    }));
  }

}
