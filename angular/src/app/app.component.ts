import {Component, OnInit} from '@angular/core';
import {UserService} from './services/user.service';
import {User} from './models/user';

@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.scss']
})
export class AppComponent implements OnInit {

  siteName = 'Espace Membre @ l\'éléfàn';
  projectName = 'L\'éléfàn';
  mainColor = '#51CAE9';

  user: User;

  constructor(private userService: UserService) {
  }

  ngOnInit(): void {
    this.userService.getUser().subscribe(user => this.user = user);
  }

}
