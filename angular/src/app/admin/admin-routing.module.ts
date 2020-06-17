import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { HomeAdminComponent } from './containers/home-admin/home-admin.component';

const routes: Routes = [
  {
    path: '', children: [
      {
        path: '', component: HomeAdminComponent
      },
      { path: '**', redirectTo: 'app/not-found' }
    ]

  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class AdminRoutingModule {
}
