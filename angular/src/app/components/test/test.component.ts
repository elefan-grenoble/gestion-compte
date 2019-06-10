import {Component, OnInit} from '@angular/core';
import {AuthService} from '../../services/auth.service';
import {Auth} from '../../models/auth';

@Component({
  selector: 'app-test',
  templateUrl: './test.component.html',
  styleUrls: ['./test.component.scss']
})
export class TestComponent implements OnInit {

  auth: Auth;

  constructor(private authService: AuthService) { }

  ngOnInit() {
    this.authService.currentUserValue.subscribe(auth => this.auth = auth);
  }

}
