import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HomeMemberComponent } from './containers/home-member/home-member.component';
import { MemberRoutingModule } from './member-routing.module';



@NgModule({
  declarations: [HomeMemberComponent],
  imports: [
    CommonModule,
    MemberRoutingModule
  ]
})
export class MemberModule { }
