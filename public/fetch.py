import urllib.request
req = urllib.request.Request('https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/Logo_of_East_Java.svg/150px-Logo_of_East_Java.svg.png', headers={'User-Agent': 'Mozilla/5.0'})
response = urllib.request.urlopen(req)
open('d:/laragon/www/dashboard/public/images/logo_jatim.png', 'wb').write(response.read())
