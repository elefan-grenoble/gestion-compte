import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { HomeMemberComponent } from './containers/home-member/home-member.component';

const routes: Routes = [
  {
    path: '', children: [
      {
        path: '', component: HomeMemberComponent
      },
      { path: '**', redirectTo: 'app/not-found' }
    ]

  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class MemberRoutingModule {
}
