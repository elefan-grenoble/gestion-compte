import { Injectable } from '@angular/core';
import { ActivatedRouteSnapshot, CanActivate, RouterStateSnapshot } from '@angular/router';
import { Observable } from 'rxjs';
import { AuthService } from '../services/auth.service';
import { map } from 'rxjs/operators';
import { Auth } from '../../api/models/auth';
import { environment } from 'src/environments/environment';

@Injectable({
  providedIn: 'root'
})
export class AuthGuard implements CanActivate {

  constructor(private authService: AuthService) {
  }

  canActivate(route: ActivatedRouteSnapshot, state: RouterStateSnapshot): Observable<boolean> {

    if (environment.devByPassSecurity) {
      return Observable.create((observer) => {
        observer.next(true);
        observer.complete();
      });
    }

    return this.authService.currentUserValue.pipe(map((auth: Auth) => {
      return !!auth.user;
    }));
  }

}
