#i/usr/bin/env bash

scriptPath="$(dirname "${0}")"
rootPath="${scriptPath}/../.."
binResourceBasePath="${rootPath}/resources/bin"
cwd="$(pwd)"
cd "${binResourceBasePath}"
binResourceBasePath="$(pwd)"
cd "${cwd}"

while read xml
do
	file="$(basename "${xml}")"
	directory="$(dirname "${xml}")"
	path="${directory#${binResourceBasePath}}"
	path="${path%/}"
	[ -z "${path}" ] && path='.'

	${rootPath}/vendor/noresources/ns-xml/ns/sh/build-php.sh \
		--parser-ns 'Parser' \
		--program-ns 'Program' \
		-x "${xml}" \
		-m "${xml%xml}php" \
		-o "${rootPath}/bin/${path}/${file%.xml}.php"
done << EOF
$(find "${binResourceBasePath}" -type f -name '*.xml')
EOF
