import json
from urllib import urlencode
import urllib2
from hashlib import sha512
from hmac import HMAC
import base64


class ApiClient(object):
    """
    Simple client which covers some basic functional
    """

    def __init__(self, auth_key, auth_secret, api_host=None, nonce=None):
        if nonce is None:
            nonce = 1
        self.nonce = nonce
        self.auth_key = auth_key
        self.auth_secret = auth_secret
        self.path = None
        self.api_prefix = '/api/v2'
        self.api_host = api_host or 'http://localhost:8000'

    def _build_query(self, req={}):
        post_data = urlencode(req) or None
        headers = {}
        headers["User-Agent"] = "NewBot"
        headers["API_KEY"] = self.auth_key
        headers["SIGNED_DATA"] = self._sign_data(req)
        return (post_data, headers)

    def _sign_data(self, data=None):
        sorted_data = []
        for p in sorted(data.items()):
            sorted_data.append('='.join(str(k) for k in p))
        return base64.b64encode(str(HMAC(self.auth_secret, self.path + '&'.join(sorted_data), sha512).digest()))

    def _perform(self, args, req_type=None, use_nonce=None, inner=False):
        if use_nonce:
            args['nonce'] = self.nonce
            self.nonce += 1

        data, headers = self._build_query(args)
        if req_type == 'GET':
            req = urllib2.Request(self.api_host + self.path + '?' + data, headers=headers)
        else:
            req = urllib2.Request(self.api_host + self.path, data, headers=headers)
        if req_type:
            req.get_method = lambda: req_type
        res = urllib2.urlopen(req, data, 60)
        result = json.load(res)
        if isinstance(result, dict) and 'invalid nonce' in result.get('error', '') and not inner:
            self.nonce = result['nonce']
            return self._perform(args, req_type, use_nonce, True)
        return result

    def _set_path(self, path):
        self.path = '%s%s' % (self.api_prefix, path)
        return self

    def exchange_depth(self, platform=None, limit=100):
        self._set_path('/exchange/depth')
        return self._perform({
            'platform': platform,
            'limit': limit,
        }, 'GET')

    def exchange_history(self, platform=None, limit=100):
        self._set_path('/exchange/history')
        return self._perform({
            'platform': platform,
            'limit': limit,
        }, 'GET')

    def exchange_ticker(self, platform=None):
        self._set_path('/exchange/ticker')
        return self._perform({
            'platform': platform,
        }, 'GET')

    def exchange_info(self):
        self._set_path('/exchange/info')
        return self._perform({})

    def trader_info(self):
        self._set_path('/trader/info')
        return self._perform({}, 'GET', use_nonce=True)

    def list_active_orders(self, limit=100, to_id=None):
        request_data = {
            'limit': limit
        }
        if to_id is not None:
            request_data['to_id'] = to_id

        self._set_path('/trader/orders')
        return self._perform(request_data, 'GET', use_nonce=True)

    def create_order(self, amount=None, cost=None, order_type=None, platform=None):
        self._set_path('/trader/orders')
        return self._perform({
            'amount': amount,
            'cost': cost,
            'type': order_type,
            'platform': platform
        }, use_nonce=True)

    def remove_order(self, order_id):
        self._set_path('/trader/remove_order')
        return self._perform({
            'order_id': order_id
        }, use_nonce=True)

    def get_trade_history(self, limit=100, to_id=None):
        request_data = {
            'limit': limit
        }
        if to_id is not None:
            request_data['to_id'] = to_id
        self._set_path('/trader/history')
        return self._perform(request_data, 'GET', use_nonce=True)


if __name__ == '__main__':
    auth_key = '86a6a2e66e586bf937cc'
    secret = 'ebef82aa5cf823a8751132daa948161cfb60f77a'
    client = ApiClient(auth_key, secret, api_host='https://coin.mx')

    print '-------------------------get depth--------------------------------------'
    print client.exchange_depth('BTCUSD', 10)

    print '-------------------------get last 10 operations-------------------------'
    print client.exchange_history('BTCUSD', 10)

    print '-------------------------get ticker info--------------------------------'
    print client.exchange_ticker('BTCUSD')

    print '-------------------------get exchange info------------------------------'
    print client.exchange_info()

    print '-------------------------list active orders-----------------------------'
    print client.list_active_orders()

    print '-------------------------create new limit order-------------------------'
    order = client.create_order(amount=1, cost=1, order_type='BUY', platform='BTCUSD')
    order2 = client.create_order(amount=0.5, cost=1, order_type='SELL', platform='BTCUSD')
    print order

    print '-------------------------removing new order ----------------------------'
    print client.remove_order(order['order_id'])
    print client.remove_order(order2['order_id'])

    print '-------------------------trader info----------------------------'
    print client.trader_info()

    print client.get_trade_history(100)
