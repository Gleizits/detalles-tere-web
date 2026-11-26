import { expect, test } from '@playwright/test';

test('la portada pública carga completa y es responsive', async ({ page }) => {
  await page.goto('/');

  await expect(page).toHaveTitle(/DetallesTereTS/);
  await expect(page.getByRole('heading', { level: 1 })).toContainText('La fiesta ordenada');
  await expect(page.locator('iframe[title="Mapa de Ica, Perú"]')).toBeVisible();
  await expect(page.locator('a.lp-whatsapp')).toHaveAttribute('href', /wa\.me\/51932677948\?text=/);
  await expect(page.locator('.lp-card')).toHaveCount(6);
  await expect(page.locator('input[name="csrfToken"]')).toHaveCount(1);
  await expect(page.locator('img[alt*="Universidad Privada San Juan Bautista"]')).toHaveCount(1);
  await expect(page.locator('.lp-manuales-top a[href="/manual-usuario"]')).toBeVisible();
  await expect(page.locator('.lp-manuales-top a[href="/manual-programador"]')).toBeVisible();
  await expect(page.getByText('Correo de destino')).toHaveCount(0);

  for (const width of [360, 768, 1366]) {
    await page.setViewportSize({ width, height: 900 });
    const desborda = await page.evaluate(
      () => document.documentElement.scrollWidth > window.innerWidth + 1,
    );
    expect(desborda, `desbordamiento horizontal a ${width}px`).toBe(false);
  }
});

test('el contador de visitas incrementa entre recargas', async ({ page }) => {
  await page.goto('/');
  const primera = await page.locator('.lp-visita strong').innerText();

  await page.reload();
  const segunda = await page.locator('.lp-visita strong').innerText();

  expect(Number(segunda.replace(/\D/g, ''))).toBe(Number(primera.replace(/\D/g, '')) + 1);
});

test('el login público y sus assets responden', async ({ page, request }) => {
  await page.goto('/login');
  await expect(page.locator('input[name="csrfToken"]')).toHaveCount(1);

  for (const asset of [
    '/assets/css/landing.css',
    '/assets/js/landing.js',
    '/assets/css/paginacion.css',
    '/assets/js/paginacion.js',
    '/assets/img/logo-upsjb.png',
  ]) {
    const respuesta = await request.get(asset);
    expect(respuesta.ok(), `${asset} debe responder 200`).toBe(true);
  }
});

test('los POST sin token CSRF son rechazados', async ({ page }) => {
  await page.goto('/');

  for (const ruta of ['/contacto', '/login']) {
    const respuesta = await page.request.post(ruta, {
      form: { nombre: 'Prueba', correo: 'prueba@example.com', mensaje: 'Mensaje de prueba' },
    });
    expect(respuesta.status(), `${ruta} sin CSRF`).toBe(403);
    expect(await respuesta.text()).toContain('Token de seguridad');
  }
});

test('los manuales son públicos y una ruta inexistente da 404', async ({ page, request }) => {
  const respuesta = await page.goto('/manual-usuario');
  expect(respuesta?.status()).toBe(200);
  await expect(page).toHaveURL(/\/manual-usuario$/);
  await expect(page.getByRole('heading', { level: 1 })).toContainText('Cómo trabajar');

  const programador = await request.get('/manual-programador');
  expect(programador.status()).toBe(200);
  expect(await programador.text()).toContain('Arquitectura y operación');

  const inexistente = await page.request.get('/no-existe');
  expect(inexistente.status()).toBe(404);
  expect(await inexistente.text()).toContain('Página no encontrada');
});

test('el formulario prepara un correo a Geissel.Espino@upsjb.edu.pe sin API externa', async ({ page }) => {
  await page.goto('/');
  const csrfToken = await page.locator('input[name="csrfToken"]').inputValue();

  const respuesta = await page.request.post('/contacto', {
    form: {
      csrfToken,
      nombre: 'Geissel Prueba',
      correo: 'prueba@example.com',
      mensaje: 'Mensaje de prueba para verificar el formulario.',
    },
  });

  expect(respuesta.status()).toBe(200);
  const html = await respuesta.text();
  expect(html).toContain('mailto:Geissel.Espino@upsjb.edu.pe');
  expect(html).not.toContain('Correo de destino');
});
