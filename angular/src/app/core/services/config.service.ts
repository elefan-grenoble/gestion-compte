import { Injectable } from '@angular/core';
import { Config } from '../../api/models/config';
import { HttpClient } from '@angular/common/http';

@Injectable({
  providedIn: 'root'
})
export class ConfigService {

  private config: Config;

  private fallbackConfig: Config = {
    main_color: 'red',
    project_name: 'elefan',
    project_url: 'http://localhost',
    project_url_display: 'http://localhost',
    site_name: 'elefan'
  };

  constructor(private http: HttpClient) {
  }

  // This is the method you want to call at bootstrap
  // Important: It should return a Promise
  load(): Promise<Config> {

    this.config = this.fallbackConfig;

    return this.http
      .get<Config>('/api/config')
      .toPromise()
      .then((data: any) => this.config = data)
      .catch((err: any) => Promise.resolve());
  }

  get Config(): Config {
    return this.config;
  }
}
