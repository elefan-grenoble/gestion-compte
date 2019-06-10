import {Component, OnInit} from '@angular/core';
import {User} from './models/user';
import {AuthService} from './services/auth.service';
import {ServiceService} from './services/service.service';
import {Service} from './models/service';
import {ConfigService} from './services/config.service';
import {Config} from './models/config';

@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.scss']
})
export class AppComponent implements OnInit {

  config: Config;

  today: number = Date.now();

  user: User;
  services: Service[];

  constructor(private authService: AuthService,
              private serviceService: ServiceService,
              private configService: ConfigService) {
    this.config = configService.config;
  }

  ngOnInit(): void {
    this.authService.currentUserValue.subscribe(auth => this.user = auth.user);
    this.serviceService.getServices().subscribe(services => this.services = services);
  }

}
