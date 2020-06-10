import {Component, OnInit} from '@angular/core';
import {User} from './api/models/user';
import {AuthService} from './core/services/auth.service';
import {ServiceService} from './core/services/service.service';
import {Service} from './api/models/service';
import {ConfigService} from './core/services/config.service';
import {Config} from './api/models/config';

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
    this.config = configService.Config;
  }

  ngOnInit(): void {
    this.authService.currentUserValue.subscribe(auth => this.user = auth.user);
    this.serviceService.getServices().subscribe(services => this.services = services);
  }

}
