import {NgModule} from '@angular/core';
import {RouterModule, Routes} from '@angular/router';
import {AuthGuard} from './guards/auth.guard';
import {TestComponent} from './components/test/test.component';

const routes: Routes = [
  {
    path: '', children: [
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
