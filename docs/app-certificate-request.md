# App Certificate Request

Diese Notiz haelt den lokalen Ablauf fuer den Nextcloud
`app-certificate-requests`-Request von WorkTimePunch fest. Der private Key darf
nicht in dieses Repository oder in den Certificate-Request-Fork committed
werden.

## Stand vom 2026-07-08

App-ID: `worktimepunch`

Lokale Dateien im WorkTimePunch-Arbeitsverzeichnis:

```text
worktimepunch.csr  CSR fuer den Nextcloud Certificate Request
worktimepunch.key  privater RSA-Key, lokal behalten und sichern
```

CSR-Metadaten:

```bash
openssl req -in worktimepunch.csr -noout -subject -nameopt RFC2253
```

Ergebnis:

```text
subject=CN=worktimepunch
```

Weitere relevante CSR-Eigenschaften aus `openssl req -text`:

```text
Public Key Algorithm: rsaEncryption
Public-Key: 4096 bit
Signature Algorithm: sha256WithRSAEncryption
```

## Erzeugung von Key und CSR

Falls der Request reproduziert werden muss:

```bash
cd /home/mletford/code/WorkTimePunch
openssl req -new -newkey rsa:4096 -nodes \
  -keyout worktimepunch.key \
  -out worktimepunch.csr \
  -subj "/CN=worktimepunch"
chmod 600 worktimepunch.key
```

Der Key `worktimepunch.key` ist dauerhaft fuer App-Signaturen aufzubewahren.
Wenn er verloren geht, muss ein neues Zertifikat beantragt werden.

## Certificate-Request-Fork

Lokaler Checkout:

```text
/home/mletford/code/WorkTimePunch/app-certificate-requests
```

Remotes:

```text
origin    https://github.com/Bacaloo/app-certificate-requests.git
upstream  https://github.com/nextcloud/app-certificate-requests.git
```

Branch:

```text
add-worktimepunch-csr
```

Commit:

```text
c26d695 Add certificate request for worktimepunch
```

Commit-Inhalt:

```text
worktimepunch/worktimepunch.csr
```

Reproduktion des Branch-Inhalts:

```bash
cd /home/mletford/code/WorkTimePunch/app-certificate-requests
git fetch upstream master
git switch -c add-worktimepunch-csr upstream/master
mkdir -p worktimepunch
cp /home/mletford/code/WorkTimePunch/worktimepunch.csr worktimepunch/worktimepunch.csr
git add worktimepunch/worktimepunch.csr
git commit -m "Add certificate request for worktimepunch"
git push -u origin add-worktimepunch-csr
```

Ein Pull Request gegen `nextcloud/app-certificate-requests:master` war zum
Zeitpunkt dieser Notiz noch nicht angelegt.

## Nach Zertifikatserteilung

Nach Merge des Certificate-Request-PRs stellt Nextcloud ein Zertifikat fuer die
App-ID bereit. Fuer spaetere App-Updates wird kein neuer Certificate Request
benoetigt, solange App-ID und Key beibehalten werden. Updates werden mit dem
vorhandenen privaten Key und Zertifikat signiert.

Der private Key sollte ausserhalb des Repositories gesichert werden, zum
Beispiel unter:

```text
~/.nextcloud/certificates/worktimepunch.key
```

Das Zertifikat kann daneben abgelegt werden:

```text
~/.nextcloud/certificates/worktimepunch.crt
```

Release-Artefakte duerfen weder `worktimepunch.key`, `.git` noch den
`app-certificate-requests`-Checkout enthalten.
