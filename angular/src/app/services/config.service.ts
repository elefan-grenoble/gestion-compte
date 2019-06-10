import {Injectable} from '@angular/core';
import {Config} from '../models/config';
import {HttpClient} from '@angular/common/http';

@Injectable({
  providedIn: 'root'
})
export class ConfigService {

  private _config: Config;

  constructor(private http: HttpClient) {
  }

  // This is the method you want to call at bootstrap
  // Important: It should return a Promise
  load(): Promise<Config> {

    this._config = null;

    return this.http
      .get<Config>('/api/config')
      .toPromise()
      .then((data: any) => this._config = data)
      .catch((err: any) => Promise.resolve());
  }

  get config(): Config {
    return this._config;
  }
}
