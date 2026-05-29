# TODO - SSL Matcher: Public Key + Private Key

## Backend
- [ ] Add new route in `routes/web.php` under `ssl-matcher` prefix: `POST /match-pub-priv`.
- [ ] Add `matchPublicKeyPrivateKey(Request $request)` in `app/Http/Controllers/SslMatcherController.php`.
- [ ] Ensure JSON response includes `public_key`, `private_key`, `pub_modulus_hash`, `key_modulus_hash`, `match`, `message`.

## Frontend
- [ ] Create new JS file `public/assets/js/ssl-matcher.js`.
- [ ] Move existing SSL matcher page JS out of `resources/views/ssl-matcher/index.blade.php` into the new JS file.
- [ ] Add new tab + panel in `resources/views/ssl-matcher/index.blade.php` for “Public Key + Private Key”.
- [ ] Wire the button to `matchPubPriv()` implemented in `ssl-matcher.js`.
- [ ] Ensure results rendering works for certificate/private/public/certs/c (new mode) without breaking existing modes.

## Validation
- [ ] Smoke test in browser:
  - [ ] Cert + Private Key
  - [ ] Cert + Public Key
  - [ ] Certificate vs Certificate
  - [ ] Public Key + Private Key
- [ ] Confirm error toasts appear when inputs missing.

