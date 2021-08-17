#!/bin/bash
#
# Create the following certs:
# * CA Root
# * CA Signer
# * Email (S/MIME)
# * TLS   (https webhost)
#
# http://pki-tutorial.readthedocs.org/en/latest/simple/
#

####################################################

# root dir
ssl_root_dir="/etc/ssl/Simple_Inc"

# root and signing  !!DO NOT REGEN till 2030!! pw=secret
create_root_ca=0
create_signing_ca=0

# email and tls
auto=0
create_tls_certs=1
create_email_certs=0
create_revoke_certs=0

# alt formats
create_crl=0
create_der=0
create_der_crl=0
create_pem=1
create_pkcs12=0
create_pkcs7=0

# user info
user="chris.pollitt"
user_full="Chris Pollitt"

# domain info
declare -A domains
  cpanel='cs16.uhcloud.com'
cml_omit='^(cmnet.whatwelove.org)\.'
wwl_omit='^(ul|u-l|universal-laughter|landmarkcourt)\.'
# --auto--
if [[ $auto == 1 ]]; then
  # local
  for domain in $(perl -MGetHosts -le 'print "".join("\n",get_apache_vhosts())'|grep -Pv "$cml_omit"); do
    domains["$domain"]=""
  done
  # wwl
  domains['whatwelove.org']=$(get_subdomains.php $cpanel 'whatwelove.org'|grep -Pv "$wwl_omit")
# --manual--
else
  domains["mesh"]=""
  domains["cmnet6.landmarkcourt.ca"]=""
fi

# defaults
default_bits=$(( 1024 * 4 ))
default_days=$(( 365 * 10 )) 
default_md="sha256" 
view_results=0

####################################################

# 1. Prep
echo "######## 1. Prep ########"

# 1.1 Change to SSL cert dir
echo "#### 1.1 Change to SSL cert dir ####"

mkdir -p $ssl_root_dir
cd $ssl_root_dir
mkdir -p etc

######################
# 2. Create Root CA
if [[ $create_root_ca == 1 ]]; then

echo "######## 2. Create Root CA ########"

# 2.1 Create Root CA Configuration File
echo "#### 2.1 Create Root CA Configuration File ####"

cat > etc/root-ca.conf <<_EOF_
# Simple Root CA

# The [default] section contains global constants that can be referred to from
# the entire configuration file. It may also hold settings pertaining to more
# than one openssl command.

[ default ]
ca                      = root-ca               # CA name
dir                     = .                     # Top dir

# The next part of the configuration file is used by the openssl req command.
# It defines the CA's key pair, its DN, and the desired extensions for the CA
# certificate.

[ req ]
default_bits            = $default_bits         # RSA key size
encrypt_key             = yes                   # Protect private key
default_md              = $default_md           # MD to use
utf8                    = yes                   # Input is UTF-8
string_mask             = utf8only              # Emit UTF-8 strings
prompt                  = no                    # Don't prompt for DN
distinguished_name      = ca_dn                 # DN section
req_extensions          = ca_reqext             # Desired extensions

[ ca_dn ]
0.domainComponent       = "org"
1.domainComponent       = "simple"
2.domainComponent       = "www"
organizationName        = "Simple Inc"
organizationalUnitName  = "Simple Root CA"
commonName              = "Simple Root CA"

[ ca_reqext ]
keyUsage                = critical,keyCertSign,cRLSign
basicConstraints        = critical,CA:true
subjectKeyIdentifier    = hash

# The remainder of the configuration file is used by the openssl ca command.
# The CA section defines the locations of CA assets, as well as the policies
# applying to the CA.

[ ca ]
default_ca              = root_ca               # The default CA section

[ root_ca ]
certificate             = \$dir/ca/\$ca.crt              # The CA cert
private_key             = \$dir/ca/\$ca/private/\$ca.key # CA private key
new_certs_dir           = \$dir/ca/\$ca                  # Certificate archive
serial                  = \$dir/ca/\$ca/db/\$ca.crt.srl  # Serial number file
crlnumber               = \$dir/ca/\$ca/db/\$ca.crl.srl  # CRL number file
database                = \$dir/ca/\$ca/db/\$ca.db       # Index file
unique_subject          = no                    # Require unique subject
default_days            = $default_days         # How long to certify for
default_md              = $default_md           # MD to use
policy                  = match_pol             # Default naming policy
email_in_dn             = no                    # Add email to cert DN
preserve                = no                    # Keep passed DN ordering
name_opt                = ca_default            # Subject DN display options
cert_opt                = ca_default            # Certificate display options
copy_extensions         = none                  # Copy extensions from CSR
x509_extensions         = signing_ca_ext        # Default cert extensions
default_crl_days        = 365                   # How long before next CRL
crl_extensions          = crl_ext               # CRL extensions

# Naming policies control which parts of a DN end up in the certificate and
# under what circumstances certification should be denied.

[ match_pol ]
domainComponent         = match                 # Must match 'simple.org'
organizationName        = match                 # Must match 'Simple Inc'
organizationalUnitName  = optional              # Included if present
commonName              = supplied              # Must be present

[ any_pol ]
domainComponent         = supplied              # Must be present
organizationName        = supplied              # Must be present
organizationalUnitName  = optional              # Included if present
commonName              = supplied              # Must be present
countryName             = optional
stateOrProvinceName     = optional
localityName            = optional
emailAddress            = optional

# Certificate extensions define what types of certificates the CA is able to
# create.

[ root_ca_ext ]
keyUsage                = critical,keyCertSign,cRLSign
basicConstraints        = critical,CA:true
subjectKeyIdentifier    = hash
authorityKeyIdentifier  = keyid:always

[ signing_ca_ext ]
keyUsage                = critical,keyCertSign,cRLSign
basicConstraints        = critical,CA:true,pathlen:0
subjectKeyIdentifier    = hash
authorityKeyIdentifier  = keyid:always

# CRL extensions exist solely to point to the CA certificate that has issued
# the CRL.

[ crl_ext ]
authorityKeyIdentifier  = keyid:always
_EOF_

# 2.2 Create Root CA directories
echo "#### 2.2 Create Root CA directories ####"

mkdir -p ca/root-ca/private ca/root-ca/db crl certs
chmod 700 ca/root-ca/private

# 2.3 Create Root CA database
echo "#### 2.3 Create Root CA database ####"

cp /dev/null ca/root-ca/db/root-ca.db
cp /dev/null ca/root-ca/db/root-ca.db.attr
echo 01 > ca/root-ca/db/root-ca.crt.srl
echo 01 > ca/root-ca/db/root-ca.crl.srl

# 2.4 Create Root CA request
echo "#### 2.4 Create Root CA request ####"

openssl \
    req -new \
    -config etc/root-ca.conf \
    -out ca/root-ca.csr \
    -keyout ca/root-ca/private/root-ca.key
    
# 2.5 Create Root CA certificate
echo "#### 2.5 Create Root CA certificate ####"

openssl \
    ca -selfsign \
    -config etc/root-ca.conf \
    -in ca/root-ca.csr \
    -out ca/root-ca.crt \
    -extensions root_ca_ext

fi

######################
# 3. Create Signing CA
if [[ $create_signing_ca == 1 ]]; then
    
echo "######## 3. Create Signing CA ########"

# 3.1 Create Signing CA Configuration File
echo "#### 3.1 Create Signing CA Configuration File ####"

cat > etc/signing-ca.conf <<_EOF_
# Simple Signing CA

# The [default] section contains global constants that can be referred to from
# the entire configuration file. It may also hold settings pertaining to more
# than one openssl command.

[ default ]
ca                      = signing-ca            # CA name
dir                     = .                     # Top dir

# The next part of the configuration file is used by the openssl req command.
# It defines the CA's key pair, its DN, and the desired extensions for the CA
# certificate.

[ req ]
default_bits            = $default_bits         # RSA key size
encrypt_key             = yes                   # Protect private key
default_md              = $default_md           # MD to use
utf8                    = yes                   # Input is UTF-8
string_mask             = utf8only              # Emit UTF-8 strings
prompt                  = no                    # Don't prompt for DN
distinguished_name      = ca_dn                 # DN section
req_extensions          = ca_reqext             # Desired extensions

[ ca_dn ]
0.domainComponent       = "org"
1.domainComponent       = "simple"
2.domainComponent       = "www"
organizationName        = "Simple Inc"
organizationalUnitName  = "Simple Signing CA"
commonName              = "Simple Signing CA"

[ ca_reqext ]
keyUsage                = critical,keyCertSign,cRLSign
basicConstraints        = critical,CA:true,pathlen:0
subjectKeyIdentifier    = hash

# The remainder of the configuration file is used by the openssl ca command.
# The CA section defines the locations of CA assets, as well as the policies
# applying to the CA.

[ ca ]
default_ca              = signing_ca            # The default CA section

[ signing_ca ]
certificate             = \$dir/ca/\$ca.crt              # The CA cert
private_key             = \$dir/ca/\$ca/private/\$ca.key # CA private key
new_certs_dir           = \$dir/ca/\$ca                  # Certificate archive
serial                  = \$dir/ca/\$ca/db/\$ca.crt.srl  # Serial number file
crlnumber               = \$dir/ca/\$ca/db/\$ca.crl.srl  # CRL number file
database                = \$dir/ca/\$ca/db/\$ca.db       # Index file
unique_subject          = no                    # Require unique subject
default_days            = $default_days         # How long to certify for
default_md              = $default_md           # MD to use
policy                  = any_pol               # Default naming policy
email_in_dn             = no                    # Add email to cert DN
preserve                = no                    # Keep passed DN ordering
name_opt                = ca_default            # Subject DN display options
cert_opt                = ca_default            # Certificate display options
copy_extensions         = copy                  # Copy extensions from CSR
x509_extensions         = email_ext             # Default cert extensions
default_crl_days        = 7                     # How long before next CRL
crl_extensions          = crl_ext               # CRL extensions

# Naming policies control which parts of a DN end up in the certificate and
# under what circumstances certification should be denied.

[ match_pol ]
domainComponent         = match                 # Must match 'simple.org'
organizationName        = match                 # Must match 'Simple Inc'
organizationalUnitName  = optional              # Included if present
commonName              = supplied              # Must be present

[ any_pol ]
domainComponent         = supplied              # Must be present
organizationName        = supplied              # Must be present
organizationalUnitName  = optional              # Included if present
commonName              = supplied              # Must be present
countryName             = optional
stateOrProvinceName     = optional
localityName            = optional
emailAddress            = optional

# Certificate extensions define what types of certificates the CA is able to
# create.

[ email_ext ]
keyUsage                = critical,digitalSignature,keyEncipherment
basicConstraints        = CA:false
extendedKeyUsage        = emailProtection,clientAuth
subjectKeyIdentifier    = hash
authorityKeyIdentifier  = keyid:always

[ server_ext ]
keyUsage                = critical,digitalSignature,keyEncipherment
basicConstraints        = CA:false
extendedKeyUsage        = serverAuth,clientAuth
subjectKeyIdentifier    = hash
authorityKeyIdentifier  = keyid:always

# CRL extensions exist solely to point to the CA certificate that has issued
# the CRL.

[ crl_ext ]
authorityKeyIdentifier  = keyid:always

_EOF_

# 3.2 Create Signing CA directories
echo "#### 3.2 Create Signing CA directories ####"

mkdir -p ca/signing-ca/private ca/signing-ca/db crl certs
chmod 700 ca/signing-ca/private

# 3.3 Create Signing CA database
echo "#### 3.3 Create Signing CA database ####"

cp /dev/null ca/signing-ca/db/signing-ca.db
cp /dev/null ca/signing-ca/db/signing-ca.db.attr
echo 01 > ca/signing-ca/db/signing-ca.crt.srl
echo 01 > ca/signing-ca/db/signing-ca.crl.srl

# 3.4 Create Signing CA request
echo "#### 3.4 Create Signing CA request ####"

openssl req -new \
    -config etc/signing-ca.conf \
    -out ca/signing-ca.csr \
    -keyout ca/signing-ca/private/signing-ca.key
    
# 3.5 Create Signing CA certificate
echo "#### 3.5 Create Signing CA certificate ####"

openssl ca \
    -config etc/root-ca.conf \
    -in ca/signing-ca.csr \
    -out ca/signing-ca.crt \
    -extensions signing_ca_ext

fi
    
######################
# 4. Operate Signing CA
echo "######## 4. Operate Signing CA ########"

#############
# 4.1 Revoke Signing CA certificate
if [[ $create_revoke_certs == 1 ]]; then

echo "## 4.1 Revoke Signing CA certificate ##"

openssl ca \
    -config etc/signing-ca.conf \
    -revoke ca/signing-ca/01.pem \
    -crl_reason superseded

fi

#############
# 4.2 Create CA CRL
if [[ $create_crl == 1 ]]; then

echo "## 4.2 Create CA CRL ##"

openssl ca -gencrl \
    -config etc/signing-ca.conf \
    -out crl/signing-ca.crl

fi
    
#############
# loop over domains
for domain in "${!domains[@]}"; do
echo "#### domain=$domain ####"

#########
# 4.3.1 Create Email certificate request Configuration File
if [[ $create_email_certs == 1 ]]; then

echo "## 4.3.1 Create Email certificate request Configuration File ##"

domain_full=$domain
domain_full=${domain_full#*.}
domain_full=${domain_full%.*}
duser="${user}_${domain#*.}"
cat > etc/${duser//./-}.conf <<_EOF_
# Email certificate request

# This file is used by the openssl req command. Since we cannot know the DN in
# advance the user is prompted for DN information.

[ req ]
default_bits            = $default_bits         # RSA key size
encrypt_key             = yes                   # Protect private key
default_md              = $default_md           # MD to use
utf8                    = yes                   # Input is UTF-8
string_mask             = utf8only              # Emit UTF-8 strings
prompt                  = no                    # Don't prompt for DN
distinguished_name      = email_dn              # DN template
req_extensions          = email_reqext          # Desired extensions

[ email_dn ]
0.domainComponent       = "${domain##*.}"
1.domainComponent       = "$domain_full"
organizationName        = "${domain_full^} Inc"
commonName              = "$user_full"
emailAddress            = "$user@${domain#*.}"

[ email_reqext ]
keyUsage                = critical,digitalSignature,keyEncipherment
extendedKeyUsage        = emailProtection,clientAuth
subjectKeyIdentifier    = hash
subjectAltName          = email:move

_EOF_

# 4.3.2 Create Email request
echo "## 4.3.2 Create Email request ##"

openssl req -new \
    -config etc/${duser//./-}.conf \
    -out certs/${duser//./-}.csr \
    -keyout certs/${duser//./-}.key
    
# 4.3.3 Create Email certificate
echo "## 4.3.3 Create Email certificate ##"

openssl ca \
    -config etc/signing-ca.conf \
    -in certs/${duser//./-}.csr \
    -out certs/${duser//./-}.crt \
    -extensions email_ext

fi

#########
# 4.4.1 Create TLS server certificate request Configuration File
if [[ $create_tls_certs == 1 ]]; then

echo "## 4.4.1 Create TLS server certificate request Configuration File ##"

cat > etc/${domain//./-}.conf <<_EOF_
# TLS server certificate request

# This file is used by the openssl req command.

[ req ]
default_bits            = $default_bits         # RSA key size
encrypt_key             = no                    # Protect private key
default_md              = $default_md           # MD to use
utf8                    = yes                   # Input is UTF-8
string_mask             = utf8only              # Emit UTF-8 strings
prompt                  = no                    # Don't prompt for DN
distinguished_name      = server_dn             # DN template
req_extensions          = server_reqext         # Desired extensions

[ server_reqext ]
keyUsage                = critical,digitalSignature,keyEncipherment
extendedKeyUsage        = serverAuth,clientAuth
subjectKeyIdentifier    = hash
subjectAltName          = @san

_EOF_


if [[ $domain == *.*.*.* ]]; then
# too many
echo "error: too many domain levels: $domain"
exit 1
  
elif [[ $domain == *.*.* ]]; then
# three (www.foo.com) #####

if [[ ${domains[$domain]} != "" ]]; then
  echo "error: subdomains can only be include in a two level domain: $domain"
  exit 1
fi

if [[ $domain == matchall.*.* ]]; then
# wildcard match #####
  
domain_full=$domain
domain_full=${domain_full#*.}
domain_full=${domain_full%.*}
domain_name=$domain
domain_name=${domain_name#*.}
cat >> etc/${domain//./-}.conf <<_EOF_
[ server_dn ]
0.domainComponent       = "${domain_name##*.}"
1.domainComponent       = "$domain_full"
organizationName        = "${domain_full^} Inc"
commonName              = "*.$domain_name"

[ san ]
DNS.1 = $domain_name
_EOF_

else
# regular domain #####

domain_full=$domain
domain_full=${domain_full#*.}
domain_full=${domain_full%.*}
cat >> etc/${domain//./-}.conf <<_EOF_
[ server_dn ]
0.domainComponent       = "${domain##*.}"
1.domainComponent       = "$domain_full"
2.domainComponent       = "${domain%%.*}"
organizationName        = "${domain_full^} Inc"
commonName              = "$domain"

[ san ]
DNS.1 = $domain
DNS.2 = www.$domain
DNS.3 = ${domain#*.}
_EOF_


fi

elif [[ $domain == *.* ]]; then
# two (foo.com) #########
  
domain_full=$domain
domain_full=${domain_full%.*}
cat >> etc/${domain//./-}.conf <<_EOF_
[ server_dn ]
0.domainComponent       = "${domain#*.}"
1.domainComponent       = "${domain%.*}"
organizationName        = "${domain_full^} Inc"
commonName              = "$domain"

[ san ]
_EOF_

san=${domains[$domain]}

if [[ $san == "" ]]; then

echo "DNS.1 = $domain"     >> etc/${domain//./-}.conf
echo "DNS.2 = www.$domain" >> etc/${domain//./-}.conf

else

(( i=1 ))
for dns in $san; do
echo "DNS.$i = $dns"       >> etc/${domain//./-}.conf
(( i++ ))
echo "DNS.$i = www.$dns"   >> etc/${domain//./-}.conf
(( i++ ))
done

fi

else
# one (foo) ###########

if [[ ${domains[$domain]} != "" ]]; then
  echo "error: subdomains can only be include in a two level domain: $domain"
  exit 1
fi
  
domain_full=$domain
cat >> etc/${domain//./-}.conf <<_EOF_
[ server_dn ]
0.domainComponent       = "$domain"
organizationName        = "${domain_full^} Inc"
commonName              = "$domain"

[ san ]
DNS.1 = $domain
_EOF_

fi

# 4.4.2 Create TLS server request
echo "## 4.4.2 Create TLS server request ##"

openssl req -new \
    -config etc/${domain//./-}.conf \
    -out certs/${domain//./-}.csr \
    -keyout certs/${domain//./-}.key
if  [[ $? -ne 0 ]]; then
  echo "Error: exiting"
  exit 1
fi

# 4.4.3 Create TLS server certificate
echo "## 4.4.3 Create TLS server certificate ##"

openssl ca \
    -config etc/signing-ca.conf \
    -in certs/${domain//./-}.csr \
    -out certs/${domain//./-}.crt \
    -extensions server_ext
if  [[ $? -ne 0 ]]; then
  echo "Error: exiting"
  exit 1
fi

fi

done 

######################
# 5. Output Formats
echo "######## 5. Output Formats ########"

# 5.1 Create DER CA CRL
if [[ $create_der_crl == 1 ]]; then

if [[ $create_signing_ca == 1 ]]; then

echo "#### 5.1 Create DER CA CRL ####" # (overwrites file!)

openssl crl \
    -in  crl/signing-ca.crl \
    -out crl/signing-ca.crl \
    -outform der
    
fi

fi

# 5.2 Create PKCS#7 CA bundle
if [[ $create_pkcs7 == 1 ]]; then

if [[ $create_signing_ca == 1 ]]; then

echo "#### 5.2 Create PKCS#7 CA bundle ####"

openssl crl2pkcs7 -nocrl \
    -certfile ca/signing-ca.crt \
    -certfile ca/root-ca.crt \
    -out      ca/signing-ca-chain.p7c \
    -outform der

fi

fi

# 5.3 Create PEM CA bundle
if [[ $create_pem == 1 ]]; then

echo "#### 5.3 Create PEM CA bundle ####"

if [[ $create_signing_ca == 1 ]]; then

cat ca/signing-ca.crt \
    ca/root-ca.crt > \
    ca/signing-ca-chain.pem

fi

fi

#############
# loop over domains
for domain in "${!domains[@]}"; do
echo "#### domain=$domain ####"

# 5.4.1 Create DER Email certificate
if [[ $create_der == 1 ]]; then

if [[ $create_email_certs == 1 ]]; then

echo "#### 5.4.1 Create DER Email certificate ####"

openssl x509 \
    -in  certs/${duser//./-}.crt \
    -out certs/${duser//./-}.cer \
    -outform der
    
fi

fi

# 5.4.2 Create PKCS#12 Email bundle
if [[ $create_pkcs12 == 1 ]]; then
  
if [[ $create_email_certs == 1 ]]; then

echo "#### 5.4.2 Create PKCS#12 Email bundle ####"

openssl pkcs12 -export \
    -name "$user_full" \
    -inkey certs/${duser//./-}.key \
    -in  certs/${duser//./-}.crt \
    -out certs/${duser//./-}.p12
    
fi

fi

# 5.4.3 Create PEM Email bundle
if [[ $create_pem == 1 ]]; then

if [[ $create_email_certs == 1 ]]; then

echo "#### 5.4.3 Create PEM Email bundle ####"

cat certs/${duser//./-}.key \
    certs/${duser//./-}.crt > \
    certs/${duser//./-}.pem

fi

fi

done

#######################
# 6. View Results
if [[ $view_results == 1 ]]; then

echo "######## 6. View Results ########"

# 6.1 View DER CA CRL
if [[ $create_der_crl == 1 ]]; then

echo "#### 6.1 View DER CA CRL ####"

openssl crl \
    -in crl/signing-ca.crl \
    -inform der \
    -noout \
    -text

fi
  
# 6.2 View PKCS#7 CA bundle
if [[ $create_pkcs7 == 1 ]]; then

echo "#### 6.2 View PKCS#7 CA bundle ####"

openssl pkcs7 \
    -in ca/signing-ca-chain.p7c \
    -inform der \
    -noout \
    -text \
    -print_certs
    
fi

#############
# loop over domains
for domain in "${!domains[@]}"; do
echo "#### domain=$domain ####"

#######
# 6.3.1 View Email request
if [[ $create_email_certs == 1 ]]; then

echo "#### 6.3.1 View Email request ####"

openssl req \
    -in certs/${duser//./-}.csr \
    -noout \
    -text
    
# 6.3.2 View Email certificate
echo "#### 6.3.2 View Email certificate ####"

openssl x509 \
    -in certs/${duser//./-}.crt \
    -noout \
    -text
 

# 6.3.3 View PKCS#12 Email bundle
if [[ $create_pkcs12 == 1 ]]; then

echo "#### 6.3.3 View PKCS#12 Email bundle ####"

openssl pkcs12 \
    -in certs/${duser//./-}.p12 \
    -nodes \
    -info

fi

fi

######
# 6.4.1 View TLS request
if [[ $create_tls_certs == 1 ]]; then

echo "#### 6.4.1 View TLS request ####"

openssl req \
    -in certs/${domain//./-}.csr \
    -noout \
    -text
    
# 6.4.2 View TLS certificate
echo "#### 6.4.2 View TLS certificate ####"

openssl x509 \
    -in certs/${domain//./-}.crt \
    -noout \
    -text
 
fi

done

fi

##########################
