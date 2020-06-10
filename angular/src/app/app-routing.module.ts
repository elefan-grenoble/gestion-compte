import {NgModule} from '@angular/core';
import {RouterModule, Routes} from '@angular/router';
import {AuthGuard} from './core/guards/auth.guard';
import {TestComponent} from './member/components/test/test.component';
import { HomeComponent } from './home/containers/home/home.component';

const routes: Routes = [
  {
    path: '', children: [
      {
        path: '', component: HomeComponent
      },
      {
        path: 'admin', loadChildren: './admin/admin.module#AdminModule', canActivate: [AuthGuard]
      },
      {
        path: 'member', loadChildren: './member/member.module#MemberModule', canActivate: [AuthGuard]
      },
      {path: 'app/test', canActivate: [AuthGuard], component: TestComponent},
      {path: '**', redirectTo: 'app/not-found'}
    ]

  },

];

@NgModule({
  imports: [RouterModule.forRoot(routes)],
  exports: [RouterModule]
})
export class AppRoutingModule {
}
