#!/bin/sh

java -jar ./yuicompressor-2.4.8.jar ./jsroll.app.js -o ./jsroll.app.tmp.js
id="\$Id: jsroll.app.min.js"
version="0.1.0"
status="beta"
echo "
 /**
 * @app $id
 * @category RIA (Rich Internet Application) / SPA (Single-page Application)
 *
 * Классы RIA / SPA 
 * @author Андрей Новиков andrey (at) novikov (dot) be
 * @status $status
 * @version $version
 * @revision $id 0004 $(date +"%d/%m/%Y %H:%M":%S)Z $
 */
" > ../js/jsroll.app.min.js
cat ./jsroll.app.tmp.js >> ../js/jsroll.app.min.js
rm ./jsroll.app.tmp.js
#./../git add .
# Subresource Integrity
cat ../js/jsroll.app.min.js| openssl dgst -sha384 -binary | openssl base64 -A > jsroll.app.min.sha384
