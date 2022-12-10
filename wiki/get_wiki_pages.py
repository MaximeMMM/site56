from xml.dom.minidom import parseString
import csv
import time
import requests
from pathlib import Path

intFoundRedirect = 0
intFoundRedirectAndAsc = 0
intFoundJustAsc = 0
arrNotFound = []
def requestWiki(pageTitle,direction="desc",recursionDepth=0):
	if(recursionDepth>5):
		return direction, None
	dataToResquest = {"pages":pageTitle,
			"offset":"2008-01-03T00:00:00Z",
			"dir":direction,
			"limit":1,
			"action":"submit",
			"title":"Special:Export"}

	urlToRequest = "https://en.wikipedia.org/w/index.php"
	r = requests.post(urlToRequest, data=dataToResquest)
	
	dom = parseString(r.text)		
	textTag = dom.getElementsByTagName("text")
	if(len(textTag)==0 or "deleted" in textTag[0].attributes):
		if(direction=="desc"):
			return requestWiki(pageTitle,"asc",recursionDepth+1)
		else:
			return direction,None
	#verifica se eh redirect
	#eh redirect por tag?	
	el = dom.getElementsByTagName("redirect")
	redirTitle = None
	if(len(el)>0):
		redirTitle = el[0].attributes["title"].value
	else:
		textTag = dom.getElementsByTagName("text")
		textWiki = textTag[0].childNodes[0].data.strip()
		#print("Text wiki: "+textWiki)		
		#o texto tem algum redirect? 				
		if(textWiki.lower().startswith("#redirect")):
			openColchete = textWiki.find("[[")
			fechaColchete = textWiki.find("]]")
			if(openColchete>0 and fechaColchete>0):
				linkStr = textWiki[openColchete+2:fechaColchete]
				redirTitle = linkStr

	#caso seja redirect, navegar no redirect 	
	if(redirTitle != None):				
		direction,dom = requestWiki(redirTitle,"desc",recursionDepth+1)
		if(direction=="desc"):
			print(strTitle+": Redir desc!")
			#intFoundRedirect+=1
		else:
			print(strTitle+": Redir asc!")
			#intFoundRedirectAndAsc+=1
	elif(direction=="asc"):
		print(strTitle+": Just asc!")
	return direction,dom
with open('wikipedia_data_sample.csv') as csvfile:
	wikiArticles = list(csv.reader(csvfile))#, delimiter=' ', quotechar='|')
	#procura no cabeçalho o rev id
	iColReview = 0
	for iCol,val in enumerate(wikiArticles[0]):
		if(val == "hist_review_count"):
			iColReview = iCol
			break
	i = 1

	for article in wikiArticles[1:]:
		intPageId = article[0]
		strTitle = article[1]
		strFileOutput = "sampleWiki/"+str(intPageId)+".txt"
		my_file = Path(strFileOutput)
		if(my_file.is_file()):
			continue
		#prepare and send the request
		intRevCount = article[iColReview]
		
		direction,dom = requestWiki(strTitle)	
		if(dom != None):		
			
			textTag = dom.getElementsByTagName("text")

			#grava o texto
			textWiki = textTag[0].childNodes[0].data
			textTag = dom.getElementsByTagName("timestamp")
			timestampArt = textTag[0].childNodes[0].data
			print("Article #"+str(i)+": "+strTitle+" id:"+intPageId+" from timestamp:"+timestampArt+" Rev count: "+str(intRevCount))
			with open(strFileOutput,"w") as f:
				f.write(textWiki)
			
		else:
			print(strTitle+": Not found!")
			arrNotFound.append(strTitle)

		time.sleep(1)
		i=i+1
		if(i%10==0):
			with open("log.txt","w") as f:
				strL="Found redirect: "+str(intFoundRedirect)
				strL+="\nFound redirect ASC: "+str(intFoundRedirectAndAsc)
				strL+="\nJust ASC :"+str(intFoundJustAsc)
				strL+="\nnot found: "+str(arrNotFound)
				print(strL)
				f.write(strL)
		

