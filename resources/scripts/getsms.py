#!/usr/bin/env python3
from huawei_lte_api.Client import Client
from huawei_lte_api.AuthorizedConnection import AuthorizedConnection
import json
import sys

def Clean_JSON(wrongJSON):
	while True:
		try:
			result = json.loads(wrongJSON)   # try to parse...
			return result
			break                    # parsing worked -> exit loop
		except Exception as e:
			unexp = int(re.findall(r'\(char (\d+)\)', str(e))[0])
			unesc = wrongJSON.rfind(r'"', 0, unexp)
			wrongJSON = s[:unesc] + r'\"' + s[unesc+1:]
			closg = wrongJSON.find(r'"', unesc + 2)
			wrongJSON = s[:closg] + r'\"' + s[closg+1:]
			return wrongJSON 
if len(sys.argv) == 4:  
	ip = sys.argv[1]
	login = sys.argv[2]
	pwd = sys.argv[3]
	list = []
	try:
		connection = AuthorizedConnection('http://'+login+':'+pwd+'@'+ip)
		client = Client(connection)
		

		try:
			list.append(json.dumps(client.user.state_login()))
		except:
			list.append('{"state_login()": "Not supported"}')

		try:
			list.append(json.dumps(client.sms.sms_count()))
		except:
			list.append('{"sms_count()": "Not supported"}')
			
		try:
			#list.append(Clean_JSON(json.dumps(client.sms.get_sms_list())))
			list.append(json.dumps(client.sms.get_sms_list()))
			#list.append(json.dumps(client.sms.get_sms_list(1, 2, 1, 0, 0, 1)))
		except:
			list.append('{"get_sms_list()": "Not supported"}')
              

		client.user.logout()

	except:
		list.append(sys.exc_info())

	print(list)
else:
	print("No parameter has been included")