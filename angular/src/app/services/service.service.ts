import { Injectable } from '@angular/core';
import {HttpClient} from '@angular/common/http';
import {Service} from '../models/service';

@Injectable({
  providedIn: 'root'
})
export class ServiceService {

  constructor(private http: HttpClient) { }

  getServices() {
    return this.http.get<Service[]>('/api/services');
  }
}
