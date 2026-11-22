# create_ca_ssl_certs-si.sh

Single script, toggle-driven, that builds a local "Simple Inc" PKI and issues
certs off it. Based on http://pki-tutorial.readthedocs.org/en/latest/simple/.

Two-tier CA: **Root CA** (self-signed, offline-ish, `pathlen:0` signer below
it) → **Signing CA** (day-to-day issuer) → leaf certs (TLS / S-MIME / code
signing).

## Layout

```
Simple_Inc/
├── bin/create_ca_ssl_certs-si.sh   # this script
├── etc/                            # generated openssl configs (*.conf)
├── ca/                             # root-ca.*, signing-ca.*, CA db/serial
├── certs/                          # issued certs, keys, csrs, bundles
└── crl/                            # revocation lists
```

## Toggles (top of script)

| Variable | Purpose |
|---|---|
| `create_root_ca` | Generate the Root CA. **Do once.** Re-running invalidates everything issued under the old root. |
| `create_signing_ca` | Generate the Signing CA (signed by Root). Same one-shot caveat. |
| `create_tls_certs` | Issue TLS server certs for each entry in `domains[]`. |
| `create_email_certs` | Issue S/MIME certs (email signing + encryption) for `$user`. |
| `create_code_signing_certs` | Issue a code-signing cert for `$user`. |
| `create_revoke_certs` | Revoke a cert (edit the path in section 4.1 first). |
| `create_crl` / `create_der_crl` | Emit a CRL (PEM / DER). |
| `create_pem` / `create_der` / `create_pkcs12` / `create_pkcs7` | Output formats for whatever was issued above. |

Everything defaults to `0` except `create_tls_certs` and `create_pem` — flip
on only what you need for a given run.

`user` / `user_full` set the identity used for email and code-signing certs.
`domains{}` (manual block, or `auto=1` to pull from Apache vhosts) drives TLS
certs — supports bare host (`foo`), two-level (`foo.com`), and three-level
incl. `matchall.*` wildcards.

## Root/Signing CA — do this first, once

```bash
create_root_ca=1
create_signing_ca=1
create_tls_certs=0
```

Run it, then flip both back to `0`. **Do not regenerate** — every cert
issued downstream chains to this key. Root CA private key lives in
`ca/root-ca/private/root-ca.key`; the whole point of the two-tier setup is
that it never has to touch day-to-day signing.

Trust the root once per machine/browser you want it recognized on:
`ca/root-ca.crt`.

## TLS certs

Default mode. Add hosts to the `domains{}` block, run with
`create_tls_certs=1`. Produces `certs/<domain>.key` + `.crt`, SANs filled in
per the domain's dot-count (see comments in the script for the exact rules).

## S/MIME (email)

```bash
create_email_certs=1
create_pkcs12=1   # if you need a .p12 to import into a mail client
```

Produces `certs/<user>_<domain>.{key,crt,p12}` with
`emailProtection,clientAuth` EKU. Import the `.p12` into Outlook/Thunderbird/
Apple Mail; import `ca/root-ca.crt` as a trusted root first so the client
doesn't flag it.

## Code signing

```bash
create_code_signing_certs=1
create_pkcs12=1   # for Windows
create_pem=1      # for Linux
```

Produces, keyed on `$user` (not per-domain — a signing identity isn't tied
to a hostname):

- `certs/<user>-codesign.{key,crt,csr}` — raw material
- `certs/<user>-codesign.p12` — **Windows**, `signtool`
- `certs/<user>-codesign.pem` — **Linux** (key+cert bundle), `osslsigncode`,
  `sbsign`/`pesign`, kernel module `sign-file`

EKU is plain `codeSigning` (works for both `signtool` and `osslsigncode` —
no need for the legacy `msCodeInd`/`msCodeCom` OIDs).

### Windows: trust + sign

Import once, per machine:

```powershell
Import-Certificate -FilePath ca\root-ca.crt      -CertStoreLocation Cert:\CurrentUser\Root
Import-Certificate -FilePath ca\signing-ca.crt   -CertStoreLocation Cert:\CurrentUser\CA
```

Then sign:

```powershell
signtool sign /f certs\<user>-codesign.p12 /p <password> /fd sha256 yourfile.exe
signtool verify /pa yourfile.exe
```

Without the two imports above, Windows will show the signature as present
but from an untrusted/unknown publisher.

### Linux: sign

```bash
osslsigncode sign \
  -certs certs/<user>-codesign.pem \
  -key   certs/<user>-codesign.pem \
  -in  yourfile.exe -out yourfile-signed.exe
```

## Notes

- Self-signed root — fine for lab/local use, will never be trusted by a
  third party (browsers, OS vendors, Windows SmartScreen reputation, etc.)
  without manually importing `root-ca.crt` on every machine that needs to
  trust it.
- `default_days = 3650` (10y) on everything issued — check `default_bits` /
  `default_md` at the top if you want to tune key size or hash.
- Re-running the script with `create_root_ca=0` / `create_signing_ca=0` is
  safe and idempotent for leaf certs — it only reissues what's toggled on.
