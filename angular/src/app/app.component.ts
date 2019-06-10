import {Component, OnInit} from '@angular/core';
import {User} from './models/user';
import {AuthService} from './services/auth.service';

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

  constructor(private authService: AuthService) {
  }

  ngOnInit(): void {
    this.authService.currentUserValue.subscribe(auth => this.user = auth.user);
  }

}
