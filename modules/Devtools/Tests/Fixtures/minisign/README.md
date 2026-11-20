# minisign test vectors (throwaway keys)

Made with the `minisign` 0.11 CLI on 2026-11-20 for `PackageSigningTest`. **Test-only keys**: they
sign nothing real and must never be trusted by a site (`packages.publicKeys`).

```bash
minisign -G -W -p plain.pub -s plain.key                       # unencrypted key
minisign -G -p enc.pub -s enc.key                              # password: test-password
printf 'hello package\n' > data.txt
minisign -S -s plain.key -m data.txt -t 'Tasks 1.0.0' -x data.plain.minisig
minisign -S -s enc.key   -m data.txt -t 'Tasks 1.0.0' -x data.enc.minisig
```

Ed25519 is deterministic, so `PackageSigner` must produce these signatures byte for byte.
