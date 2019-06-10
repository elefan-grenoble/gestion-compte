import {Component, OnInit} from '@angular/core';
import {User} from './models/user';
import {AuthService} from './services/auth.service';
import {ServiceService} from './services/service.service';
import {Service} from './models/service';

@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.scss']
})
export class AppComponent implements OnInit {

  siteName = 'Espace Membre @ l\'éléfàn';
  projectName = 'L\'éléfàn';
  projectUrl = 'https://localcoop.local/';
  projectUrlDisplay = 'localcoop.local';
  mainColor = '#51CAE9';

  today: number = Date.now();

  user: User;
  services: Service[];

  constructor(private authService: AuthService,
              private serviceService: ServiceService) {
  }

  ngOnInit(): void {
    this.authService.currentUserValue.subscribe(auth => this.user = auth.user);
    this.serviceService.getServices().subscribe(services => this.services = services);
  }

}
