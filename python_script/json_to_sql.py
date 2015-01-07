#!/usr/bin/env python2
# -*- coding:utf-8 -*-

"""
JSON to SQL converter

input : Magic the Gatering json file(mtgjson.com) with extras.
"""

__author__ = "Kazunori Kimura <kazunori.abu@gmail.com>"
__status__ = "wip"
__version__ = "0.0.1"
__date__ = "29 December 2014"


import json
import sys
import codecs
from optparse import OptionParser


sys.stdout = codecs.getwriter('utf-8')(sys.stdout)


TYPES_TO_CODE = {
    "Creature": 0x01,
    "Land": 0x02,
    "Artifact": 0x04,
    "Enchantment": 0x08,
    "Instant": 0x10,
    "Sorcery": 0x20,
    "Planeswalker": 0x40,
    "Tribal": 0x80
}


LANG_TO_CODE = {
    "Chinese Traditional": "zh_tw",
    "Chinese Simplified": "zh_cn",
    "French": "fr",
    "German": "de",
    "Italian": "it",
    "Japanese": "ja",
    "Korean": "ko",
    "Portuguese (Brazil)": "pt",
    "Portuguese": "pt",
    "Russian": "ru",
    "Spanish": "es"
}


COLOR_TO_CODE = {
    "White": 'W',
    "Blue": 'U',
    "Red": 'R',
    "Black": 'B',
    "Green": 'G'
}

LANGUAGE_NAMES = LANG_TO_CODE.keys()


SQL_TEMPLATE = "INSERT INTO %s ( %s ) VALUES ( %s );"


def parse_option():
    """
    command line option parse
    """

    usage = "usage: %prog [options] arg1 arg2 ..."
    parser = OptionParser(usage=usage, version=__version__)

    parser.add_option("-o", "--output",
                      action="store", dest="output_file",
                      metavar="FILE",
                      help="write output to FILE_data.sql and FILE_name.sql")

    options, args = parser.parse_args()
    option_dict = eval(str(options))
    return option_dict, args


def print_tree(t, indent=0):
    if (isinstance(t, dict)):
        for k, v in t.items():
            print " "*indent, k, ":",
            if (isinstance(v, (str, unicode, int))):
                print v
            else:
                print
                print_tree(v, indent+4)
    elif (isinstance(t, list)):
        for d in t:
            print_tree(d, indent+4)
    else:
        print " "*indent, t


def proc_json(json_file):
    try:
        with open(json_file, "r") as f:
            data = json.load(f)
            cards = data.pop('cards')
            scode = data['code']
            sname = data['name']
            block = data.get('block', 'core')
            carddata = map(proc_card, cards)
            carddata, namelist = map(list, zip(*carddata))

            map(lambda x: x.update({'set_code':quote(scode)}), carddata)

            return carddata, namelist
        
    except IOError:
        print >> sys.stderr, "File \"%s\" cannot be opened" % json_file


def proc_card(card):
    mid = card['multiverseid']
    manacost = card.get('manaCost', '')
    name = card['name']
    typecode = sum(map(lambda x: TYPES_TO_CODE[x], card['types']))
    color_list = card.get('colors', '')
    colors = ''
    num = card.get('number')
    
    if color_list != '':
        colors = map(lambda x: COLOR_TO_CODE[x], color_list)
        colors = ''.join(colors)

    cmc = card.get('cmc', 0)
    fnames = card.get('foreignNames', [])
    if fnames != []:
        fnames = map(lambda x: (LANG_TO_CODE[x['language']],
                                quote(x['name'])), fnames)
    fnames.append(('multiverseid', str(mid)))
    ret_dict = {
        'multiverseid': str(mid),
        'cardname': quote(name),
        'manacost': quote(manacost),
        'colors': quote(colors),
        'cmc': str(cmc),
        'typecode': str(typecode),
    }
    return ret_dict, dict(fnames)


def flatten(listobj):
    if isinstance(listobj, list):
        head = listobj[0]
        if len(listobj) > 1:
            for d in listobj[1:]:
                head.extend(d)
        return head
    return None


def quote(str_in):
    if "'" in str_in:
        str_in = str_in.replace("'", "&#039;")
    str_out = '"%s"' % str_in
 
    return str_out


def dump_as_sql(data, target, dest=sys.stdout):
    for d in data:
        dest.write(SQL_TEMPLATE % (target,
                                   ','.join(d.keys()),
                                   ','.join(d.values())))
        dest.write('\n')

        
if __name__ == "__main__":
    options, args = parse_option()
    data = map(proc_json, args)
    data, names = map(list, zip(*data))

    data = flatten(data)
    name = flatten(names)

    output = options.get('output_file', '')
    output_data = ''
    output_name = ''
    if output:
        output_data = codecs.open(output + '_data.sql', 'w', 'utf-8')
        output_name = codecs.open(output + '_name.sql', 'w', 'utf-8')
    else:
        output_data = sys.stdout
        output_name = sys.stdout

    dump_as_sql(data, 'carddata', output_data)
    dump_as_sql(name, 'cardname', output_name)
