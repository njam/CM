define(["CM/JsonSerialized"], function() {

  QUnit.module('CM/JsonSerialized:flat', {
    beforeEach: function() {
      var models = {
        foo: new CM_JsonSerialized_Abstract({val1: 1}),
        bar: new CM_JsonSerialized_Abstract({val1: 1})
      };

      var synced = [];
      models.foo.on('sync', function(model, syncAttributes) {
        synced.push(syncAttributes);
      });

      this.models = models;
      this.synced = synced;
    },

    afterEach: function() {
      this.models.foo.off();
      this.models.bar.off();
      this.models = null;
      this.synced = null;
    }
  });

  QUnit.test("compatible", function(assert) {
    var foo = this.models.foo;
    var bar = this.models.bar;
    assert.ok(foo.compatible(bar) && bar.compatible(foo));
    assert.ok(!foo.compatible({val1: 1}));
    assert.ok(!bar.compatible({val1: 1}));
  });

  QUnit.test("toJSON", function(assert) {
    var foo = this.models.foo;

    assert.deepEqual(foo.toJSON(), {val1: 1});

    foo.set({
      val1: 1,
      val2: {foo: 1}
    });
    assert.deepEqual(foo.toJSON(), {val1: 1, val2: {foo: 1}});
  });

  QUnit.test("equals", function(assert) {
    var foo = this.models.foo;
    var bar = this.models.bar;

    assert.ok(foo.equals(bar) && bar.equals(foo));
    assert.ok(!foo.equals());
    assert.ok(!foo.equals(null));
    assert.ok(!foo.equals({val1: 1}));
    assert.ok(!foo.equals(new CM_JsonSerialized_Abstract()));

    foo.set({val1: 2});
    assert.ok(!foo.equals(bar) && !bar.equals(foo));

    foo.set({
      val1: 1,
      val2: new CM_JsonSerialized_Abstract({val: 2})
    });
    bar.set({
      val1: 1,
      val2: new CM_JsonSerialized_Abstract({val: 2})
    });
    assert.ok(foo.equals(bar) && bar.equals(foo));

    foo.get('val2').set({val: 1});
    assert.ok(!foo.equals(bar) && !bar.equals(foo));

    foo.get('val2').set({val: 2});
    foo.set('val3', 3);
    assert.ok(!foo.equals(bar) && !bar.equals(foo));

    foo.unset('val1');
    foo.unset('val3');
    assert.ok(!foo.equals(bar) && !bar.equals(foo));

    foo.set('val1', 1);
    assert.ok(foo.equals(bar) && bar.equals(foo));

    bar.set('val3', 3);
    assert.ok(!foo.equals(bar) && !bar.equals(foo));

    bar.unset('val1');
    bar.unset('val3');
    assert.ok(!foo.equals(bar) && !bar.equals(foo));
  });


  QUnit.test("sync", function(assert) {
    var foo = this.models.foo;
    var bar = this.models.bar;
    var synced = this.synced;

    assert.throws(function() {
      foo.sync({val1: 1});
    }, /Failed to update the model, incompatible parameter/);

    foo.sync(bar);
    assert.deepEqual(synced, []);

    bar.set({val1: 3, val2: 1});
    foo.sync(bar);
    assert.equal(foo.get('val1'), 3);
    assert.equal(foo.get('val2'), 1);
    assert.deepEqual(synced, [{updated: {val1: 3}, added: {val2: 1}}]);


    bar.unset('val2');
    foo.sync(bar);
    assert.equal(foo.get('val2'), undefined);
    assert.deepEqual(synced, [
      {updated: {val1: 3}, added: {val2: 1}},
      {removed: ['val2']}
    ]);
  });

  // Nested CM_JsonSerialized_Abstract instances

  QUnit.module('CM/JsonSerialized:nested', {
    beforeEach: function() {
      var foo001 = new CM_JsonSerialized_Abstract({name: '1'});
      var foo011 = new CM_JsonSerialized_Abstract({name: '1.1'});
      var foo111 = new CM_JsonSerialized_Abstract({name: '1.1.1'});
      foo001.set('1.1', foo011);
      foo011.set('1.1.1', foo111);

      var clone001 = foo001.clone();
      var clone011 = foo011.clone();
      var clone111 = foo111.clone();
      clone001.set('1.1', clone011);
      clone011.set('1.1.1', clone111);

      var synced = {foo001: null, foo011: null, foo111: null};

      foo001.on('sync', function(model, syncAttributes) {
        synced.foo001 = syncAttributes;
      });
      foo011.on('sync', function(model, syncAttributes) {
        synced.foo011 = syncAttributes;
      });
      foo111.on('sync', function(model, syncAttributes) {
        synced.foo111 = syncAttributes;
      });

      this.models = {
        foo: {foo001: foo001, foo011: foo011, foo111: foo111},
        clone: {clone001: clone001, clone011: clone011, clone111: clone111}
      };
      this.synced = synced;
    },

    afterEach: function() {
      _.each(this.models.foo, function(model) {
        model.off();
      });
      this.models = null;
      this.synced = null;
    }
  });

  QUnit.test("toJSON", function(assert) {
    assert.deepEqual(this.models.foo.foo001.toJSON(), {
      'name': '1',
      '1.1': {
        'name': '1.1',
        '1.1.1': {
          'name': '1.1.1'
        }
      }
    });
  });

  QUnit.test("sync: change higher level child attribute", function(assert) {
    var foo001 = this.models.foo.foo001;
    var clone001 = this.models.clone.clone001;
    var clone111 = this.models.clone.clone111;
    var synced = this.synced;

    clone111.set('name', 'clone 1.1.1');
    foo001.sync(clone001);
    assert.equal(foo001.get('1.1').get('1.1.1').get('name'), 'clone 1.1.1');
    assert.deepEqual(synced.foo001, {
      updated: {
        '1.1': {
          updated: {
            '1.1.1': {
              updated: {'name': 'clone 1.1.1'}
            }
          }
        }
      }
    });
    assert.deepEqual(synced.foo011, {
      updated: {
        '1.1.1': {
          updated: {'name': 'clone 1.1.1'}
        }
      }
    });
    assert.deepEqual(synced.foo111, {
      updated: {
        'name': 'clone 1.1.1'
      }
    });
  });

  QUnit.test("sync: change middle level child attribute", function(assert) {
    var foo001 = this.models.foo.foo001;
    var clone001 = this.models.clone.clone001;
    var clone011 = this.models.clone.clone011;
    var synced = this.synced;

    clone011.set('name', 'clone 1.1');

    foo001.sync(clone001);
    assert.equal(foo001.get('1.1').get('name'), 'clone 1.1');
    assert.deepEqual(synced.foo001, {
      updated: {
        '1.1': {
          updated: {'name': 'clone 1.1'}
        }
      }
    });
    assert.deepEqual(synced.foo011, {
      updated: {'name': 'clone 1.1'}
    });
    assert.deepEqual(synced.foo111, null);
  });


  QUnit.test("sync: add child attribute", function(assert) {
    var foo001 = this.models.foo.foo001;
    var clone001 = this.models.clone.clone001;
    var clone011 = this.models.clone.clone011;
    var synced = this.synced;

    clone011.set('val1', 1);

    foo001.sync(clone001);
    assert.equal(foo001.get('1.1').get('val1'), 1);
    assert.deepEqual(synced.foo001, {
      updated: {
        '1.1': {
          added: {'val1': 1}
        }
      }
    });
    assert.deepEqual(synced.foo011, {
      added: {'val1': 1}
    });
    assert.deepEqual(synced.foo111, null);
  });

  QUnit.test("sync: add child JsonSerialized attribute", function(assert) {
    var foo001 = this.models.foo.foo001;
    var clone001 = this.models.clone.clone001;
    var clone011 = this.models.clone.clone011;
    var synced = this.synced;

    clone011.set('val1', new CM_JsonSerialized_Abstract({foo: 1}));

    foo001.sync(clone001);
    assert.ok(foo001.get('1.1').get('val1') instanceof CM_JsonSerialized_Abstract);
    assert.deepEqual(synced.foo001, {
      updated: {
        '1.1': {
          added: {
            'val1': {foo: 1}
          }
        }
      }
    });
    assert.deepEqual(synced.foo011, {
      added: {
        'val1': {foo: 1}
      }
    });
    assert.deepEqual(synced.foo111, null);
  });

  QUnit.test("sync: remove child attribute", function(assert) {
    var foo001 = this.models.foo.foo001;
    var clone001 = this.models.clone.clone001;
    var clone011 = this.models.clone.clone011;
    var synced = this.synced;

    clone011.unset('name');

    foo001.sync(clone001);
    assert.equal(foo001.get('1.1').get('name'), undefined);
    assert.deepEqual(synced.foo001, {
      updated: {
        '1.1': {
          removed: ['name']
        }
      }
    });
    assert.deepEqual(synced.foo011, {
      removed: ['name']
    });
    assert.deepEqual(synced.foo111, null);
  });


  QUnit.test("sync: remove child JsonSerialized attribute", function(assert) {
    var foo001 = this.models.foo.foo001;
    var foo111 = this.models.foo.foo111;
    var clone001 = this.models.clone.clone001;
    var clone011 = this.models.clone.clone011;
    var synced = this.synced;

    clone011.unset('1.1.1');

    var isFoo111Removed = false;
    foo111.on('remove', function() {
      isFoo111Removed = true;
    });

    foo001.sync(clone001);
    assert.equal(foo001.get('1.1').get('1.1.1'), undefined);
    assert.deepEqual(synced.foo001, {
      updated: {
        '1.1': {
          removed: ['1.1.1']
        }
      }
    });
    assert.deepEqual(synced.foo011, {
      removed: ['1.1.1']
    });
    assert.deepEqual(synced.foo111, null);
    assert.ok(isFoo111Removed);
  });
});
