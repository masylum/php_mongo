# php_mongo

php_mongo is a tiny wrapper for the php mongo client.

Its based (I would say, a shameless copy) on [simplemongophp](http://github.com/ibwhite/simplemongophp)

## how does it work?

PHPMongo is not a ORM. Is just a bunch of static functions that only share the connection.

    PHPMongo::connect(array(
        user: 'xxxxx'
        password: 'xxxxxx'
        uri: foo.domain.com
        port: 27031
        db: 'db_name'
    ));

## examples

    PHPMongo::find('cats')->sort(array('order' => 1, 'name' => 1));

    PHPMongo::group(
        'cats',
        array('dogs' => 1, 'order' => 1),
        array('count' => 0),
        "function (obj, prev) { prev.count++; }",
        $conditions
    );

    PHPMongo::mapReduce(
        'cat',
        'function(){emit("max", this.order);}',
        'function(key, values){return Math.max.apply(Math, values);}'
    )->getNext();

    PHPMongo::distinct('dogs', 'foo.bar', array(
        'foo' => false,
        'bar' => false
    ));

    PHPMongo::findOne('ads', array('location.country.woeid' => (int)$woeid, 'confirmed' => true, 'banned' => false, 'deleted' => false));

    PHPMongo::insert('contacts', array('foo' => 'bar'));

    // etc...
