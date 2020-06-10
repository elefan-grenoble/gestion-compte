import {Injectable} from '@angular/core';
import {HttpClient} from '@angular/common/http';
import {Observable} from 'rxjs';
import {Auth} from '../../api/models/auth';

@Injectable({
  providedIn: 'root'
})
export class AuthService {

  constructor(private http: HttpClient) {
  }

  get currentUserValue(): Observable<Auth> {
    return this.http.get<Auth>('/api/auth');
  }

}
