/**
 * Provides the javascript for the contacts.php script.
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

var ImpContacts = {

    _passAddresses: function()
    {
        var sa = '';

        $('selected_addresses').childElements().each(function(s) {
            if (s.value) {
                sa += s.value + '|';
            }
        });

        $('sa').setValue(sa);
    },

    sameOption: function(f, item, itemj)
    {
        var t = f + ": " + item.value,
            tj = itemj.value;

        return Try.these(
            function() {
                return (t == tj) || (decodeURIComponent(t) == decodeURIComponent(tj));
            },
            // Catch exception with NS 7.1
            function() {
                return (t == tj);
            }
        );
    },

    addAddress: function(f)
    {
        var d, l, option, s = $('search_results');

        if (!$F(s).size()) {
            alert(IMP.text.contacts_select);
        } else {
            d = $('selected_addresses');
            l = $A(d).size();
            s.childElements().each(function(i) {
                if (i.value && i.selected) {
                    if (!$A(d).any(function(j) {
                        return this.sameOption(f, i, j);
                    }, this)) {
                        option = f + ': ' + i.value;
                        d[l++] = new Option(option, option);
                    }
                }
            }, this);
        }
    },

    updateMessage: function()
    {
        if (!parent.opener) {
            alert(IMP.text.contacts_closed);
            window.close();
            return;
        }

        $('selected_addresses').childElements().each(function(s) {
            var pos,
                address = s.value;

            if (!address.empty()) {
                pos = address.indexOf(':');

                $(parent.opener.document).fire('ImpContacts:update', {
                    field: address.substring(0, pos),
                    value: address.substring(pos + 2, address.length)
                });
            }
        });

        window.close();
    },

    removeAddress: function()
    {
        $('selected_addresses').childElements().each(function(o) {
            if (o.selected) {
                o.remove();
            }
        });
    },

    onDomLoad: function()
    {
        if ($('search').present()) {
            $('btn_clear').show();
        }
        $('contacts').observe('submit', this._passAddresses.bind(this));
        document.observe('click', this._clickHandler.bindAsEventListener(this));
        document.observe('dblclick', this._dblclickHandler.bindAsEventListener(this));
    },

    _clickHandler: function(e)
    {
        if (e.isRightClick()) {
            return;
        }

        var elt = e.element(), id;

        while (Object.isElement(elt)) {
            id = elt.readAttribute('id');

            switch (id) {
            case 'btn_clear':
                $('search').clear();
                break;

            case 'btn_add_to':
            case 'btn_add_cc':
            case 'btn_add_bcc':
                this.addAddress(id.substring(8));
                break;

            case 'btn_update':
                this.updateMessage();
                break;

            case 'btn_delete':
                this.removeAddress();
                break;

            case 'btn_cancel':
                window.close();
                break;
            }

            elt = elt.up();
        }
    },

    _dblclickHandler: function(e)
    {
        var elt = e.element();
        if (!elt.match('SELECT')) {
            elt = elt.up('SELECT');
        }

        switch (elt.readAttribute('id')) {
        case 'search_results':
            this.addAddress('to');
            break;

        case 'selected_addresses':
            this.removeAddress();
            break;
        }
    }

};

document.observe('dom:loaded', ImpContacts.onDomLoad.bind(ImpContacts));
