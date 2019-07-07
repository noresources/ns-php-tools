#i/usr/bin/env bash

cwd="$(pwd)"
scriptPath="$(dirname "${0}")"
rootPath="${scriptPath}/../.."
cd "${rootPath}" && rootPath="$(pwd)"
cd "${cwd}"
binResourceBasePath="${rootPath}/resources/bin"
cwd="$(pwd)"
cd "${binResourceBasePath}"
binResourceBasePath="$(pwd)"
cd "${cwd}"

# Pass 1 php
echo '-- PHP tools'
while read xml
do
	file="$(basename "${xml}")"
	echo "--- ${file}" 
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

# Pass 2: phar
echo '-- Phar'
buildPhar="${rootPath}/bin/build-phar-app.php"
[ -x "${buildPhar}" ] || exit 0

while read xml
do
	file="$(basename "${xml}")"
	echo "--- ${file}"
	directory="$(dirname "${xml}")"
	path="${directory#${binResourceBasePath}}"
	path="${path%/}"
	[ -z "${path}" ] && path='.'

	output="${rootPath}/bin/${path}/${file%.xml}.phar"
	
	buildPharOptions=(\
		--parser-ns 'Parser' \
		--program-ns 'Program' \
		--bootstrap "${rootPath}/vendor/autoload.php" \
		--ns-xml-path "${rootPath}/vendor/noresources/ns-xml/ns" \
		-x "${xml}" \
		-a "${xml%xml}php" \
		-o "${output}" \
	)
	
	#buildPharOptions=("${buildPharOptions[@]}" --verbose)
	buildPharOptions=("${buildPharOptions[@]}" --compress-files)
		
	[ -f "${xml%xml}json" ] && buildPharOptions=("${buildPharOptions[@]}" -E "${xml%xml}json")
	
	"${buildPhar}" \
		"${buildPharOptions[@]}"
done << EOF
$(find "${binResourceBasePath}" -type f -name '*.xml')
EOF

# Pass 3: phar using build-phar-app.phar
if false
then
	echo '-- Phar (bis)'
	buildPhar="${rootPath}/bin/build-phar-app.phar"
	[ -x "${buildPhar}" ] || exit 0
	
	while read xml
	do
		file="$(basename "${xml}")"
		directory="$(dirname "${xml}")"
		path="${directory#${binResourceBasePath}}"
		path="${path%/}"
		[ -z "${path}" ] && path='.'
	
		output="${rootPath}/bin/${path}/${file%.xml}.2.phar"
		
		buildPharOptions=(\
			--parser-ns 'Parser' \
			--program-ns 'Program' \
			-x "${xml}" \
			-a "${xml%xml}php" \
			-o "${output}" \
		)
		
		#buildPharOptions=("${buildPharOptions[@]}" --verbose)
		buildPharOptions=("${buildPharOptions[@]}" --compress-files)
		
		[ -f "${xml%xml}json" ] && buildPharOptions=("${buildPharOptions[@]}" -E "${xml%xml}json")
		
		"${buildPhar}" \
			"${buildPharOptions[@]}"
	done << EOF
$(find "${binResourceBasePath}" -type f -name '*.xml')
EOF

fi
