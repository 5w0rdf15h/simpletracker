# simplecounter

If you have multiple websites (say 100+) and you don't want to share information
about your visitors with Google or any other third-party service, simplecounter
is your choice! All it does is it saves all data about your visitors into text 
files which you can analyze as you wish. Further tutorials will be posted soon.


## Script installation

Open c.php and edit following settings: 

```php
define('LOG_DIRECTORY', '');
define('LOG_FILENAME', 'counter.log');
```

It is recommended to set LOG_DIRECTORY to some path which is not 
accessible from the web.

Place following code on every page where you want to track your visitors.

```html
<script type="text/javascript">document.write("<img src='/c.php?r=" + 
escape(document.referrer) + "&u=" + escape(document.URL) +"&pt=" + 
escape(document.title.substring(0,80)) + "&rnd=" + Math.random() + 
"' border='0' width='0' height='0' style='width: 0; height: 0;' />");</script>
```