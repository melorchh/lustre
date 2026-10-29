content = open('lustre-backend/pages/get_available_times.php','r',encoding='utf-8').read()
old = '''        <<<<<<< HEAD
        // The full 2-hour visit must fit before the shift ends
        if ( + (2 * 60 * 60) > ) {
=======
        // The full 1-hour visit must fit before the shift ends
        if ( + (60 * 60) > ) {
>>>>>>> origin/main'''
new = '''        // The full 1-hour visit must fit before the shift ends
        if ( + (60 * 60) > ) {'''
if old in content:
    content = content.replace(old, new)
    open('lustre-backend/pages/get_available_times.php','w',encoding='utf-8').write(content)
    print('replaced')
else:
    print('not found')
