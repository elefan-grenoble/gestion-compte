import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HomeAdminComponent } from './containers/home-admin/home-admin.component';
import { AdminRoutingModule } from './admin-routing.module';


@NgModule({
  declarations: [HomeAdminComponent],
  imports: [
    CommonModule,
    AdminRoutingModule
  ]
})
export class AdminModule { }
