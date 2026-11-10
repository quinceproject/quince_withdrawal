# Third-party notices

Quince Withdrawal makes its PDF documents with mPDF. mPDF and the packages it needs
ship with the module in the `libs/` folder, each under its own licence. mPDF is
licensed under the GNU General Public License v2.0 only, which is why the whole
module is released under that licence too (see [LICENSE](LICENSE)).

| Component | Version | Folder | Licence |
|-----------|---------|--------|---------|
| [mPDF](https://github.com/mpdf/mpdf) | 8.3.1 | `libs/mpdf/mpdf` | GPL-2.0-only |
| [mpdf/psr-http-message-shim](https://github.com/mpdf/psr-http-message-shim) | 2.0.1 | `libs/mpdf/psr-http-message-shim` | MIT |
| [mpdf/psr-log-aware-trait](https://github.com/mpdf/psr-log-aware-trait) | 3.0.0 | `libs/mpdf/psr-log-aware-trait` | MIT |
| [mpdf/qrcode](https://github.com/mpdf/qrcode) | | `libs/mpdf/qrcode` | LGPL-2.1-or-later |
| [myclabs/deep-copy](https://github.com/myclabs/DeepCopy) | 1.14.0 | `libs/myclabs/deep-copy` | MIT |
| [paragonie/random_compat](https://github.com/paragonie/random_compat) | 9.99.100 | `libs/paragonie/random_compat` | MIT |
| [psr/http-message](https://github.com/php-fig/http-message) | 2.0 | `libs/psr/http-message` | MIT |
| [psr/log](https://github.com/php-fig/log) | 3.0.2 | `libs/psr/log` | MIT |
| [setasign/fpdi](https://github.com/Setasign/FPDI) | 2.6.8 | `libs/setasign/fpdi` | MIT |
| Composer autoloader | | `libs/composer` | MIT |

Notes:

- `mpdf/qrcode` sits in `libs/` but the module does not load it. Its `composer.json`
  declares LGPL-2.1-or-later; the licence file shipped with it holds the LGPL v3 text.
- `mpdf/psr-http-message-shim` and `mpdf/psr-log-aware-trait` declare the MIT licence
  in their `composer.json` and ship no licence file of their own. Their authors are
  listed there (Mark Dorison, Kristofer Widholm and, for the shim, Nigel Cunningham).
- The fonts in `libs/mpdf/mpdf/ttfonts` keep their own licences: the DejaVu fonts
  under the Bitstream Vera and Arev licences described in `DejaVuinfo.txt`, and the
  XB Riyaz fonts under the SIL Open Font License 1.1 described in
  `XW Zar Font Info.txt`.

The licence texts below are copied from the files shipped with each package. The full
GPL v2 text of mPDF is the same as this module's [LICENSE](LICENSE).

## myclabs/deep-copy

```
The MIT License (MIT)

Copyright (c) 2013 My C-Sense

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
```

## paragonie/random_compat

```
The MIT License (MIT)

Copyright (c) 2015 Paragon Initiative Enterprises

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

```

## psr/http-message

```
Copyright (c) 2014 PHP Framework Interoperability Group

Permission is hereby granted, free of charge, to any person obtaining a copy 
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights 
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell 
copies of the Software, and to permit persons to whom the Software is 
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in 
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
```

## psr/log

```
Copyright (c) 2012 PHP Framework Interoperability Group

Permission is hereby granted, free of charge, to any person obtaining a copy 
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights 
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell 
copies of the Software, and to permit persons to whom the Software is 
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in 
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
```

## setasign/fpdi

```
The MIT License (MIT)

Copyright (c) 2024 Setasign GmbH & Co. KG, https://www.setasign.com

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
```

## Composer autoloader

```

Copyright (c) Nils Adermann, Jordi Boggiano

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is furnished
to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.

```

## mpdf/qrcode

The licence file shipped in `libs/mpdf/qrcode/LICENSE` (GNU Lesser General Public License v3) applies; it is not repeated here because of its length.
