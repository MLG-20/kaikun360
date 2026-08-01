import { TestBed } from '@angular/core/testing';
import { App } from './app';

describe('App', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [App],
    }).compileComponents();
  });

  it('should create the app', () => {
    const fixture = TestBed.createComponent(App);
    const app = fixture.componentInstance;
    expect(app).toBeTruthy();
  });

  /**
   * Ce test vérifiait le `<h1>Hello, kaikun360</h1>` du gabarit livré par la
   * CLI — supprimé dès F0.2, quand la racine est devenue un simple point de
   * routage. Il échouait depuis, sans rien dire d'utile. On l'aligne sur ce que
   * la racine rend réellement : l'emplacement de routage et le bouton global.
   */
  it('monte le point de routage et le bouton « retour en haut »', async () => {
    const fixture = TestBed.createComponent(App);
    await fixture.whenStable();
    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.querySelector('router-outlet')).toBeTruthy();
    expect(compiled.querySelector('app-scroll-top')).toBeTruthy();
  });
});
