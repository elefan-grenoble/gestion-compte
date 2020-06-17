import { BrowserModule, HammerModule } from '@angular/platform-browser';
import { APP_INITIALIZER, NgModule } from '@angular/core';
import { AppRoutingModule } from './app-routing.module';
import { AppComponent } from './app.component';
import { HttpClientModule } from '@angular/common/http';
import { TestComponent } from './member/components/test/test.component';
import { ConfigService } from './core/services/config.service';
import { HomeModule } from './home/home.module';
import { CoreModule } from './core/core.module';

export function loadConfig(config: ConfigService) {
  return () => config.load();
}

@NgModule({
  declarations: [
    AppComponent,
    TestComponent
  ],
  imports: [
    BrowserModule,
    CoreModule,
    AppRoutingModule,
    HammerModule,
    HttpClientModule,
    HomeModule
  ],
  providers: [
    {
      provide: APP_INITIALIZER,
      useFactory: loadConfig,
      deps: [ConfigService],
      multi: true
    }
  ],
  bootstrap: [AppComponent]
})
export class AppModule {
}
