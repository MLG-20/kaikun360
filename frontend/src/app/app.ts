import { Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';

import { FooterComponent } from './shared/components/footer/footer';
import { HeaderComponent } from './shared/components/header/header';
import { OrbitHeroComponent } from './shared/components/orbit-hero/orbit-hero';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, HeaderComponent, FooterComponent, OrbitHeroComponent],
  templateUrl: './app.html',
  styleUrl: './app.scss',
})
export class App {
  protected readonly title = 'kaikun360';
}
