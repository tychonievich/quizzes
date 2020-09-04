#!/bin/bash

echo ======== Ensuring www-data write access to directories ========

chmod 777 log cache && echo 'Succcess!' || echo 'Failure! Results not recorded until fixed'

echo ======== Getting Michelf\'s Markdown processor ========

wget -nc "https://raw.githubusercontent.com/michelf/php-markdown/lib/Michelf/Markdown.php" -P Michelf
wget -nc "https://raw.githubusercontent.com/michelf/php-markdown/lib/Michelf/MarkdownExtra.php" -P Michelf
wget -nc "https://raw.githubusercontent.com/michelf/php-markdown/lib/Michelf/MarkdownInterface.php" -P Michelf


if grep 'server-side' course.json | grep -q true
then
    echo ======== Getting server-side KaTeX ========

    sudo npm install --global katex
    echo
    if [ ! -d katex ]
    then
        mkdir katex
        cp /usr/local/lib/node_modules/katex/dist/katex.min.css katex/ \
            || cp /usr/lib/node_modules/katex/dist/katex.min.css katex/
        cp -r /usr/local/lib/node_modeuls/katex/dist/fonts katex/ \
            || cp -r /usr/lib/node_modeuls/katex/dist/fonts katex/
    else
        echo 'KaTeX fonts and CSS already there; not retrieving.'
        echo
    fi
else
    echo ======== Getting client-side KaTeX ========

    if [ ! -e .htKaTeX.json ]
    then
        wget https://api.github.com/repos/KaTeX/KaTeX/releases/latest -O .htKaTeX.json
        url=$(cat .htKaTeX.json | egrep -o '"browser_download_url" *: *"[^"]*"' | cut -d'"' -f4 | grep '.tar')
        wget -nc $url
        [ -d katex ] && rm -rf katex
        tar xvf katex.tar*
        rm katex.tar*
        echo
    else
        echo 'Client-side KaTeX already there; not retrieving.'
        echo
    fi

fi

exit
