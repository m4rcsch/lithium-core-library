# Install APC and Mongo extensions

wget -q http://pecl.php.net/get/APC-3.1.9.tgz
tar -xzf APC-3.1.9.tgz
sh -c "cd APC-3.1.9 && phpize && ./configure && make -s && sudo make -s install"
echo "extension=apc.so" >> `php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||"`

wget -q http://pecl.php.net/get/mongo-1.2.7.tgz
tar -xzf mongo-1.2.7.tgz
sh -c "cd mongo-1.2.7 && phpize && ./configure && make -s && sudo make -s install"
echo "extension=mongo.so" >> `php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||"`

INIFILE=`php --ini | grep "Loaded Configuration" | awk {'print $4'}`
perl -p -i -e 's/phar\.readonly = On/phar.readonly = Off/' $INIFILE